<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestIndicators;
use App\Models\AiGenerationRun;
use App\Models\EarlyWarningIndicator;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ScenarioIndicatorPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 9 AI assist (roadmap R2.6): early warning indicators with an expected direction per quadrant
 * that tells at least two futures apart. Proposals are indicators with origin `ai` and review status
 * `proposed`; a new run replaces the AI proposals still waiting.
 */
class IndicatorSuggestionService
{
    public const NAME_LIMIT = 160;

    public function __construct(
        protected StructuredLlmClient $llm,
    ) {}

    /** @throws ValidationException without an approved framework or without AI */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        $framework = $project->framework()->first();
        if (! $framework?->isFrameworkApproved()) {
            throw ValidationException::withMessages(['ai' => 'Setujui kerangka skenario (G5) dulu sebelum meminta usulan indikator.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['indicator_suggestion_status' => LlmJobStatus::QUEUED->value, 'indicator_suggestion_error' => null]);
        SuggestIndicators::dispatch($project, $user->id);
    }

    /** Run by SuggestIndicators. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $framework = $project->framework()->firstOrFail();
        $existing = EarlyWarningIndicator::where('project_id', $project->id)->where(fn ($q) => $q->where('origin', '!=', Provenance::AI)->orWhere('review_status', 'accepted'))->pluck('name')->all();
        $inputRefs = ['project' => $project->uuid, 'framework' => $framework->uuid];

        try {
            $response = $llm->generateStructured(ScenarioIndicatorPrompt::request($framework, $existing));
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'scenario_indicators', $llm, ScenarioIndicatorPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $drafts = collect($response->data['indicators'] ?? []);
        $problem = $this->problem($drafts);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'scenario_indicators', $llm, ScenarioIndicatorPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Usulan AI ditolak: '.$problem);

            return;
        }

        app(AiGenerationRecorder::class)->completed('scenario_indicators', $response, ScenarioIndicatorPrompt::VERSION, $inputRefs, $userId, $project->id);
        $axes = ['A' => $framework->axis_a_uncertainty_id, 'B' => $framework->axis_b_uncertainty_id];
        $quadrants = $framework->quadrants()->pluck('scenario_quadrants.id', 'position');
        DB::transaction(function () use ($project, $drafts, $axes, $quadrants, $userId) {
            EarlyWarningIndicator::where('project_id', $project->id)->where('origin', Provenance::AI)->where('review_status', 'proposed')->delete();
            foreach ($drafts as $draft) {
                $indicator = EarlyWarningIndicator::create([
                    'project_id' => $project->id,
                    'name' => trim((string) $draft['name']),
                    'description' => Str::limit(trim((string) ($draft['description'] ?? '')), 2000, '') ?: null,
                    'critical_uncertainty_id' => $axes[$draft['axis'] ?? ''] ?? null,
                    'metric' => trim((string) ($draft['metric'] ?? '')) ?: null,
                    'unit' => trim((string) ($draft['unit'] ?? '')) ?: null,
                    'frequency' => $draft['frequency'],
                    'source_type' => 'manual',
                    'threshold_rule' => Str::limit(trim((string) ($draft['threshold_rule'] ?? '')), 500, '') ?: null,
                    'status' => 'active',
                    'origin' => Provenance::AI,
                    'review_status' => 'proposed',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                foreach ($draft['directions'] as $position => $direction) {
                    $indicator->directions()->create(['scenario_quadrant_id' => $quadrants[$position], 'expected_direction' => $direction]);
                }
            }
            $project->update(['indicator_suggestion_status' => LlmJobStatus::COMPLETED->value, 'indicator_suggestion_error' => null]);
        });
    }

    /** Accept one AI proposal as it is. */
    public function accept(EarlyWarningIndicator $indicator, User $user): void
    {
        if ($indicator->review_status !== 'proposed') {
            throw ValidationException::withMessages(['indicators' => 'Indikator ini sudah diterima.']);
        }
        $indicator->update(['review_status' => 'accepted', 'updated_by' => $user->id]);
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['indicator_suggestion_status' => LlmJobStatus::FAILED->value, 'indicator_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the proposals cannot be used, or null.
     *
     * @param  Collection<int, array<string, mixed>>  $drafts
     */
    private function problem(Collection $drafts): ?string
    {
        if ($drafts->isEmpty()) {
            return 'tidak ada usulan.';
        }
        if ($drafts->count() > ScenarioIndicatorPrompt::MAX_INDICATORS) {
            return 'lebih dari '.ScenarioIndicatorPrompt::MAX_INDICATORS.' usulan.';
        }
        foreach ($drafts as $draft) {
            $name = trim((string) ($draft['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > self::NAME_LIMIT) {
                return 'nama indikator kosong atau terlalu panjang.';
            }
            if (! in_array($draft['frequency'] ?? null, EarlyWarningIndicator::FREQUENCIES, true)) {
                return "frekuensi \"{$name}\" tidak dikenal.";
            }
            $directions = (array) ($draft['directions'] ?? []);
            if (collect(array_keys($directions))->sort()->values()->all() !== collect(Scenario::POSITIONS)->sort()->values()->all()) {
                return "\"{$name}\" tidak memberi arah di keempat kuadran.";
            }
            foreach ($directions as $direction) {
                if (! in_array($direction, EarlyWarningIndicator::DIRECTIONS, true)) {
                    return "arah \"{$name}\" tidak dikenal.";
                }
            }
            if (! EarlyWarningIndicatorService::discriminates(collect($directions))) {
                return "\"{$name}\" bergerak sama di semua kuadran, jadi tidak membedakan skenario.";
            }
        }

        return null;
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
