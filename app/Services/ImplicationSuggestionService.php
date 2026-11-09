<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestImplications;
use App\Models\AiGenerationRun;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ScenarioImplicationPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 7 AI assist (roadmap R2.6): opportunities and challenges per quadrant, each citing accepted
 * conditions of its own quadrant. Drafts are items with origin `ai` and review status `proposed`;
 * they count for step 7 only once accepted. A new draft replaces the AI items still waiting.
 */
class ImplicationSuggestionService
{
    public const TITLE_LIMIT = 160;

    public function __construct(
        protected StructuredLlmClient $llm,
    ) {}

    /** @throws ValidationException without an approved framework, without conditions, or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['ai' => 'Setujui kerangka skenario (G5) dulu sebelum meminta draf peluang dan tantangan.']);
        }
        if (! $this->conditionsOf($framework)->contains(fn ($rows) => $rows->isNotEmpty())) {
            throw ValidationException::withMessages(['ai' => 'Tulis dan terima kondisi skenario di langkah 5 dulu; draf AI merujuk ke kondisi itu.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['implication_suggestion_status' => LlmJobStatus::QUEUED->value, 'implication_suggestion_error' => null]);
        SuggestImplications::dispatch($project, $user->id);
    }

    /** Run by SuggestImplications. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $framework = $project->framework()->firstOrFail();
        $quadrants = $framework->quadrants()->get()->keyBy('position');

        $refs = [];
        $offered = [];
        $counter = 0;
        foreach ($this->conditionsOf($framework) as $position => $rows) {
            foreach ($rows as $condition) {
                $ref = 'C'.(++$counter);
                $refs[$ref] = ['id' => $condition->id, 'position' => $position];
                $offered[$position][] = ['ref' => $ref, 'text' => $condition->text];
            }
        }
        $inputRefs = ['project' => $project->uuid, 'framework' => $framework->uuid];

        try {
            $response = $llm->generateStructured(ScenarioImplicationPrompt::request($framework, $offered));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'scenario_implications', $llm, ScenarioImplicationPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $items = collect($response->data['items'] ?? []);
        $problem = $this->problem($items, $refs);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'scenario_implications', $llm, ScenarioImplicationPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Draf AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('scenario_implications', $response, ScenarioImplicationPrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($project, $quadrants, $items, $refs, $userId) {
            ScenarioImplication::whereIn('scenario_quadrant_id', $quadrants->pluck('id'))->where('origin', Provenance::AI)->where('review_status', 'proposed')->delete();
            foreach ($items as $item) {
                $implication = $quadrants[$item['position']]->implicationItems()->create([
                    'type' => $item['type'],
                    'title' => trim((string) $item['title']),
                    'description' => Str::limit(trim((string) ($item['description'] ?? '')), 2000, '') ?: null,
                    'impact_level' => $item['impact_level'] ?? null,
                    'origin' => Provenance::AI,
                    'review_status' => 'proposed',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $implication->conditions()->sync(array_values(array_unique(array_map(fn ($ref) => $refs[$ref]['id'], $item['condition_refs']))));
            }
            $project->update(['implication_suggestion_status' => LlmJobStatus::COMPLETED->value, 'implication_suggestion_error' => null]);
        });
    }

    /** Accept one AI draft as it is. */
    public function accept(ScenarioImplication $implication, User $user): void
    {
        if ($implication->review_status !== 'proposed') {
            throw ValidationException::withMessages(['implications' => 'Item ini sudah diterima.']);
        }
        $implication->update(['review_status' => 'accepted', 'updated_by' => $user->id]);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['implication_suggestion_status' => LlmJobStatus::FAILED->value, 'implication_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the drafts cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, array{id: int, position: string}>  $refs
     */
    private function problem(Collection $items, array $refs): ?string
    {
        if ($items->isEmpty()) {
            return 'tidak ada usulan.';
        }
        foreach ($items->countBy('position') as $position => $count) {
            if ($count > ScenarioImplicationPrompt::MAX_PER_QUADRANT) {
                return 'lebih dari '.ScenarioImplicationPrompt::MAX_PER_QUADRANT." usulan di {$position}.";
            }
        }
        foreach ($items as $item) {
            $position = (string) ($item['position'] ?? '');
            $title = trim((string) ($item['title'] ?? ''));
            if (! in_array($position, Scenario::POSITIONS, true) || ! in_array($item['type'] ?? null, ScenarioImplication::TYPES, true)) {
                return "posisi atau jenis usulan \"{$title}\" tidak dikenal.";
            }
            if ($title === '' || mb_strlen($title) > self::TITLE_LIMIT) {
                return "judul usulan di {$position} kosong atau terlalu panjang.";
            }
            if (isset($item['impact_level']) && ! in_array($item['impact_level'], ScenarioImplication::IMPACT_LEVELS, true)) {
                return "tingkat dampak \"{$title}\" tidak dikenal.";
            }
            if (($item['condition_refs'] ?? []) === []) {
                return "\"{$title}\" tidak merujuk ke kondisi.";
            }
            foreach ($item['condition_refs'] as $ref) {
                if (($refs[$ref]['position'] ?? null) !== $position) {
                    return "\"{$title}\" merujuk ke {$ref}, yang bukan kondisi kuadran {$position}.";
                }
            }
        }

        return null;
    }

    /** @return Collection<string, EloquentCollection<int, ScenarioCondition>> accepted conditions per position */
    private function conditionsOf(Scenario $framework): Collection
    {
        return $framework->quadrants()->with(['conditions' => fn ($q) => $q->where('review_status', 'accepted')])->get()
            ->mapWithKeys(fn ($quadrant) => [$quadrant->position => $quadrant->conditions]);
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
