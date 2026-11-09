<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestConditions;
use App\Models\AiGenerationRun;
use App\Models\ProjectDrivingForce;
use App\Models\Scenario;
use App\Models\ScenarioCondition;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ScenarioConditionPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 5 AI assist (roadmap R2.5): draft conditions per quadrant of an approved framework. Drafts
 * are stored as conditions with origin `ai` and review status `proposed`; they count toward step 5
 * only once accepted. A new draft replaces the AI proposals still waiting for review.
 */
class ConditionSuggestionService
{
    public function __construct(
        protected StructuredLlmClient $llm,
        protected ScenarioConditionService $conditions,
    ) {}

    /** @throws ValidationException without an approved framework or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['ai' => 'Setujui kerangka skenario (G5) dulu sebelum meminta draf kondisi.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['condition_suggestion_status' => LlmJobStatus::QUEUED->value, 'condition_suggestion_error' => null]);
        SuggestConditions::dispatch($project, $user->id);
    }

    /** Run by SuggestConditions. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $framework = $project->framework()->with('quadrants.conditions')->firstOrFail();
        $quadrants = $framework->quadrants->keyBy('position');
        $existing = $quadrants->map(fn ($quadrant) => $quadrant->conditions->where('review_status', 'accepted')->pluck('text')->values()->all())->all();

        $links = collect($this->conditions->forces($project));
        $byLabel = [];
        $forces = [];
        foreach ($links->values() as $index => $force) {
            $label = 'F'.($index + 1);
            $byLabel[$label] = $force['id'];
            $forces[] = ['label' => $label, 'keyword' => (string) $force['keyword'], 'impact' => $force['impact'], 'uncertainty' => $force['uncertainty']];
        }
        $predetermined = array_values(array_filter(array_column($this->conditions->predetermined($project), 'keyword')));
        $inputRefs = ['project' => $project->uuid, 'framework' => $framework->uuid];

        try {
            $response = $llm->generateStructured(ScenarioConditionPrompt::request($framework, $existing, $forces, $predetermined));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'scenario_conditions', $llm, ScenarioConditionPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $drafts = collect($response->data['conditions'] ?? []);
        $problem = $this->problem($drafts, $byLabel);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'scenario_conditions', $llm, ScenarioConditionPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Draf AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('scenario_conditions', $response, ScenarioConditionPrompt::VERSION, $inputRefs, $userId, $project->id);
        $forceIds = ProjectDrivingForce::whereIn('uuid', array_values($byLabel))->pluck('id', 'uuid');
        DB::transaction(function () use ($project, $quadrants, $drafts, $byLabel, $forceIds, $userId) {
            ScenarioCondition::whereIn('scenario_quadrant_id', $quadrants->pluck('id'))->where('origin', Provenance::AI)->where('review_status', 'proposed')->delete();
            foreach ($drafts as $draft) {
                $quadrant = $quadrants[$draft['position']];
                $kind = $draft['kind'];
                $quadrant->conditions()->create([
                    'kind' => $kind,
                    'text' => Str::limit(trim((string) $draft['text']), 500, ''),
                    'axis' => $kind === 'pole_expression' ? $draft['axis'] : null,
                    'project_driving_force_id' => $kind === 'force_behaviour' ? $forceIds[$byLabel[$draft['force']]] : null,
                    'sort_order' => (int) $quadrant->conditions()->max('sort_order') + 1,
                    'origin' => Provenance::AI,
                    'review_status' => 'proposed',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
            $project->update(['condition_suggestion_status' => LlmJobStatus::COMPLETED->value, 'condition_suggestion_error' => null]);
        });
    }

    /** Accept one AI draft: it becomes an ordinary condition of its quadrant. */
    public function accept(ScenarioCondition $condition, User $user): void
    {
        if ($condition->review_status !== 'proposed') {
            throw ValidationException::withMessages(['conditions' => 'Kondisi ini sudah diterima.']);
        }
        $condition->update(['review_status' => 'accepted', 'updated_by' => $user->id]);
        app(StalenessService::class)->markQuadrants($condition->quadrant()->get(), 'Kondisi berubah setelah narasi dibuat', onlyWithNarrative: true);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['condition_suggestion_status' => LlmJobStatus::FAILED->value, 'condition_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the drafts cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $drafts
     * @param  array<string, string>  $byLabel
     */
    private function problem(Collection $drafts, array $byLabel): ?string
    {
        if ($drafts->isEmpty()) {
            return 'tidak ada kondisi.';
        }
        foreach ($drafts->countBy('position') as $position => $count) {
            if ($count > ScenarioConditionPrompt::MAX_PER_QUADRANT) {
                return 'lebih dari '.ScenarioConditionPrompt::MAX_PER_QUADRANT." kondisi di {$position}.";
            }
        }
        foreach ($drafts as $draft) {
            $position = $draft['position'] ?? '';
            if (! in_array($position, Scenario::POSITIONS, true)) {
                return "posisi kuadran {$position} tidak dikenal.";
            }
            if (trim((string) ($draft['text'] ?? '')) === '') {
                return "kondisi kosong di {$position}.";
            }
            $kind = $draft['kind'] ?? '';
            if (! in_array($kind, ScenarioCondition::KINDS, true)) {
                return "jenis kondisi {$kind} tidak dikenal.";
            }
            if ($kind === 'pole_expression' && ! in_array($draft['axis'] ?? null, ['A', 'B'], true)) {
                return "kondisi kutub di {$position} tidak menyebut sumbu.";
            }
            if ($kind === 'force_behaviour' && ! isset($byLabel[$draft['force'] ?? ''])) {
                return "kondisi perilaku driving force di {$position} tidak menyebut driving force yang diberikan.";
            }
        }

        return null;
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
