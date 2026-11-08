<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Jobs\SuggestForceScores;
use App\Models\AiGenerationRun;
use App\Models\Dimension;
use App\Models\ForceAssessment;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Models\User;
use App\Services\Llm\AiGenerationRecorder;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\Prompts\ForceAssessmentPrompt;
use App\Services\Llm\StructuredLlmClient;
use App\Support\Provenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Step 3 AI assist (roadmap R2.4): impact and uncertainty suggestions for the included forces of an
 * exercise. Suggestions cite only the signals offered for their force and are stored as
 * `ai_suggestion` rows; accepting one copies it into the consensus through the normal scoring
 * rules, rejecting discards it. Nothing changes the consensus on its own.
 */
class ScoreSuggestionService
{
    /** Signals offered per force, newest first. */
    public const SIGNALS_PER_FORCE = 3;

    public function __construct(
        protected StructuredLlmClient $llm,
        protected ProjectAssessmentService $assessments,
    ) {}

    /**
     * Queue a suggestion run for the exercise.
     *
     * @throws ValidationException before the force list is agreed, without included forces or without AI
     */
    public function request(ScenarioPlanningProject $project, User $user): void
    {
        if (! $project->forcesConfirmed()) {
            throw ValidationException::withMessages(['ai' => 'Sepakati daftar driving force dulu sebelum meminta saran skor.']);
        }
        if (! $project->projectForces()->where('inclusion_status', 'included')->exists()) {
            throw ValidationException::withMessages(['ai' => 'Belum ada driving force yang disertakan.']);
        }
        if (! $this->clientFor($user->id)->isAvailable()) {
            throw ValidationException::withMessages(['ai' => 'Layanan AI belum diatur. Atur provider AI di Pengaturan.']);
        }

        $project->update(['score_suggestion_status' => LlmJobStatus::QUEUED->value, 'score_suggestion_error' => null]);
        SuggestForceScores::dispatch($project, $user->id);
    }

    /** Run by SuggestForceScores: one AI call for all included forces. */
    public function generate(ScenarioPlanningProject $project, ?int $userId): void
    {
        $llm = $this->clientFor($userId);
        $links = $this->includedForces($project);
        $forces = [];
        $refs = [];
        foreach ($links->values() as $index => $link) {
            $signals = $link->drivingForce?->supporting_signals
                ->where('review_status', 'ACCEPTED')
                ->sortByDesc('id')
                ->take(self::SIGNALS_PER_FORCE)
                ->values() ?? collect();
            $label = 'F'.($index + 1);
            $offered = [];
            foreach ($signals as $signal) {
                $ref = 'S'.(count($refs) + 1);
                $refs[$ref] = ['signal_id' => $signal->id, 'force' => $label];
                $offered[] = ['ref' => $ref, 'title' => $signal->title, 'quote' => $signal->evidence_quote];
            }
            $forces[$label] = [
                'label' => $label,
                'link' => $link,
                'keyword' => (string) $link->drivingForce?->keyword,
                'category' => $link->drivingForce?->dimension?->name,
                'description' => $link->drivingForce?->description,
                'signals' => $offered,
            ];
        }

        $inputRefs = ['project' => $project->uuid, 'forces' => $links->pluck('uuid')->values()->all()];
        $request = ForceAssessmentPrompt::request($project, array_values(array_map(fn ($force) => array_diff_key($force, ['link' => true]), $forces)));

        try {
            $response = $llm->generateStructured($request);
        } catch (LlmException $e) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::FAILED, 'force_assessment', $llm, ForceAssessmentPrompt::VERSION, $inputRefs, $e->getMessage(), $userId, $project->id);
            $this->fail($project, $e->userMessage());

            return;
        }

        $suggestions = collect($response->data['suggestions'] ?? []);
        $problem = $this->problem($suggestions, $forces, $refs);
        if ($problem !== null) {
            app(AiGenerationRecorder::class)->unsuccessful(AiGenerationRun::REJECTED, 'force_assessment', $llm, ForceAssessmentPrompt::VERSION, $inputRefs, $problem, $userId, $project->id, $response);
            $this->fail($project, 'Saran AI ditolak: '.$problem);

            return;
        }

        $run = app(AiGenerationRecorder::class)->completed('force_assessment', $response, ForceAssessmentPrompt::VERSION, $inputRefs, $userId, $project->id);
        DB::transaction(function () use ($suggestions, $forces, $refs, $run, $userId, $project) {
            foreach ($suggestions as $item) {
                $link = $forces[$item['force']]['link'];
                $link->suggestion()->delete();
                ForceAssessment::create([
                    'project_driving_force_id' => $link->id,
                    'kind' => ForceAssessment::AI_SUGGESTION,
                    'impact' => (int) $item['impact'],
                    'uncertainty' => (int) $item['uncertainty'],
                    'impact_rationale' => Str::limit((string) $item['impact_rationale'], 1000, ''),
                    'uncertainty_rationale' => Str::limit((string) $item['uncertainty_rationale'], 1000, ''),
                    'origin' => Provenance::AI,
                    'generation_run_id' => $run->id,
                    'cited_signal_ids' => array_values(array_map(fn ($ref) => $refs[$ref]['signal_id'], $item['cited_refs'] ?? [])),
                    'updated_by' => $userId,
                ]);
            }
            $project->update(['score_suggestion_status' => LlmJobStatus::COMPLETED->value, 'score_suggestion_error' => null]);
        });
    }

    /** Copy a suggestion into the consensus under the normal scoring rules (G3, staleness). Returns true when G3 was withdrawn. */
    public function accept(ProjectDrivingForce $link, User $user): bool
    {
        $suggestion = $link->suggestion()->first();
        if (! $suggestion) {
            throw ValidationException::withMessages(['ai' => 'Tidak ada saran AI untuk driving force ini.']);
        }

        $withdrawn = $this->assessments->score($link, [
            'impact' => $suggestion->impact,
            'uncertainty' => $suggestion->uncertainty,
            'impact_rationale' => $suggestion->impact_rationale,
            'uncertainty_rationale' => $suggestion->uncertainty_rationale,
        ], $user, Provenance::AI);
        $suggestion->delete();

        return $withdrawn;
    }

    public function reject(ProjectDrivingForce $link): void
    {
        $link->suggestion()->delete();
    }

    public function fail(ScenarioPlanningProject $project, string $message): void
    {
        $project->update(['score_suggestion_status' => LlmJobStatus::FAILED->value, 'score_suggestion_error' => Str::limit($message, 500, '')]);
    }

    /**
     * Why the answer cannot be used, or null: every force answered once, scores 1–10, and each
     * citation one of the signals offered for that force.
     *
     * @param  Collection<int, array<string, mixed>>  $suggestions
     * @param  array<string, array<string, mixed>>  $forces
     * @param  array<string, array{signal_id: int, force: string}>  $refs
     */
    private function problem(Collection $suggestions, array $forces, array $refs): ?string
    {
        if ($suggestions->pluck('force')->sort()->values()->all() !== collect(array_keys($forces))->sort()->values()->all()) {
            return 'tidak semua driving force mendapat tepat satu saran.';
        }
        foreach ($suggestions as $item) {
            foreach (['impact', 'uncertainty'] as $axis) {
                if (! is_int($item[$axis] ?? null) || $item[$axis] < 1 || $item[$axis] > 10) {
                    return "skor {$axis} untuk {$item['force']} di luar 1–10.";
                }
            }
            foreach ($item['cited_refs'] ?? [] as $ref) {
                if (($refs[$ref]['force'] ?? null) !== $item['force']) {
                    return "{$item['force']} mengutip {$ref}, yang tidak diberikan untuk driving force itu.";
                }
            }
        }

        return null;
    }

    /** @return Collection<int, ProjectDrivingForce> included forces in method category order */
    private function includedForces(ScenarioPlanningProject $project): Collection
    {
        $order = Dimension::ordered()->pluck('id')->flip();

        return $project->projectForces()
            ->where('inclusion_status', 'included')
            ->with(['drivingForce.dimension', 'drivingForce.supporting_signals'])
            ->get()
            ->sortBy(fn ($link) => sprintf('%03d-%s', $order[$link->drivingForce?->dimension_id] ?? 999, strtolower((string) $link->drivingForce?->keyword)))
            ->values();
    }

    protected function clientFor(?int $userId): StructuredLlmClient
    {
        return ($userId ? app(LlmClientResolver::class)->resolveForUserId($userId, false) : null) ?? $this->llm;
    }
}
