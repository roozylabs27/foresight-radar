<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestResponses;
use App\Models\AiGenerationRun;
use App\Models\Scenario;
use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\StrategicResponse;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\StrategicResponsePrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 8 AI assist (roadmap R2.6): strategic responses citing accepted opportunities and
 * challenges, with fit per quadrant. They are ordinary responses with origin `ai` and status
 * `proposed`, approved or rejected through gate G7. A new run replaces the AI responses still proposed.
 */
class ResponseSuggestionService
{
    public const TITLE_LIMIT = 160;

    public function __construct(
        protected StructuredLlmClient $llm,
    ) {}

    /** @throws ValidationException without an approved framework, without accepted implications, or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['ai' => 'Setujui kerangka skenario (G5) dulu sebelum meminta usulan respons.']);
        }
        if ($this->implicationsOf($framework)->isEmpty()) {
            throw ValidationException::withMessages(['ai' => 'Tulis dan terima peluang atau tantangan di langkah 7 dulu; usulan AI merujuk ke item itu.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['response_suggestion_status' => LlmJobStatus::QUEUED->value, 'response_suggestion_error' => null]);
        SuggestResponses::dispatch($project, $user->id);
    }

    /** Run by SuggestResponses. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $framework = $project->framework()->firstOrFail();
        $quadrants = $framework->quadrants()->pluck('id', 'position');

        $refs = [];
        $offered = [];
        foreach ($this->implicationsOf($framework)->values() as $index => $item) {
            $ref = 'I'.($index + 1);
            $refs[$ref] = $item->id;
            $offered[] = ['ref' => $ref, 'position' => (string) $item->quadrant?->position, 'type' => $item->type, 'title' => $item->title];
        }
        $existing = $project->strategicResponses()->where('status', '!=', 'rejected')->pluck('title')->all();
        $inputRefs = ['project' => $project->uuid, 'framework' => $framework->uuid];

        try {
            $response = $llm->generateStructured(StrategicResponsePrompt::request($framework, $offered, $existing));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'strategic_responses', $llm, StrategicResponsePrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $drafts = collect($response->data['responses'] ?? []);
        $problem = $this->problem($drafts, $refs);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'strategic_responses', $llm, StrategicResponsePrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Usulan AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('strategic_responses', $response, StrategicResponsePrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($project, $drafts, $refs, $quadrants, $userId) {
            $project->strategicResponses()->where('origin', Provenance::AI)->where('status', 'proposed')->delete();
            foreach ($drafts as $draft) {
                $created = StrategicResponse::create([
                    'project_id' => $project->id,
                    'title' => trim((string) $draft['title']),
                    'description' => Str::limit(trim((string) ($draft['description'] ?? '')), 2000, '') ?: null,
                    'rationale' => Str::limit(trim((string) ($draft['rationale'] ?? '')), 2000, '') ?: null,
                    'initiatives' => Str::limit(trim((string) ($draft['initiatives'] ?? '')), 3000, '') ?: null,
                    'priority' => (int) $draft['priority'],
                    'status' => 'proposed',
                    'origin' => Provenance::AI,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $created->implications()->sync(array_values(array_unique(array_map(fn ($ref) => $refs[$ref], $draft['implication_refs']))));
                foreach ($draft['fit'] as $position => $fit) {
                    $created->fits()->create(['scenario_quadrant_id' => $quadrants[$position], 'fit' => $fit]);
                }
            }
            $project->update(['response_suggestion_status' => LlmJobStatus::COMPLETED->value, 'response_suggestion_error' => null]);
        });
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['response_suggestion_status' => LlmJobStatus::FAILED->value, 'response_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the proposals cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $drafts
     * @param  array<string, int>  $refs
     */
    private function problem(Collection $drafts, array $refs): ?string
    {
        if ($drafts->isEmpty()) {
            return 'tidak ada usulan.';
        }
        if ($drafts->count() > StrategicResponsePrompt::MAX_RESPONSES) {
            return 'lebih dari '.StrategicResponsePrompt::MAX_RESPONSES.' usulan.';
        }
        foreach ($drafts as $draft) {
            $title = trim((string) ($draft['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > self::TITLE_LIMIT) {
                return 'judul respons kosong atau terlalu panjang.';
            }
            $priority = $draft['priority'] ?? null;
            if (! is_int($priority) || $priority < 1 || $priority > 5) {
                return "prioritas \"{$title}\" di luar 1–5.";
            }
            $fit = (array) ($draft['fit'] ?? []);
            if (collect(array_keys($fit))->sort()->values()->all() !== collect(Scenario::POSITIONS)->sort()->values()->all()) {
                return "\"{$title}\" tidak menilai kecocokan di keempat kuadran.";
            }
            foreach ($fit as $value) {
                if (! in_array($value, StrategicResponse::FITS, true)) {
                    return "nilai kecocokan \"{$title}\" tidak dikenal.";
                }
            }
            if (($draft['implication_refs'] ?? []) === []) {
                return "\"{$title}\" tidak merujuk ke peluang atau tantangan.";
            }
            foreach ($draft['implication_refs'] as $ref) {
                if (! isset($refs[$ref])) {
                    return "\"{$title}\" merujuk ke {$ref}, yang tidak diberikan.";
                }
            }
        }

        return null;
    }

    /** @return EloquentCollection<int, ScenarioImplication> accepted, typed implications of the framework */
    private function implicationsOf(Scenario $framework): EloquentCollection
    {
        return ScenarioImplication::whereIn('scenario_quadrant_id', $framework->quadrants()->select('scenario_quadrants.id'))
            ->where('review_status', 'accepted')
            ->whereNotNull('type')
            ->with('quadrant')
            ->orderBy('scenario_quadrant_id')
            ->orderBy('id')
            ->get();
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
