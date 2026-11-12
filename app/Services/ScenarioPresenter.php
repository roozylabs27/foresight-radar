<?php

namespace App\Services;

use App\Enums\LlmJobStatus;
use App\Models\Scenario;
use App\Services\Llm\StructuredLlmClient;

/**
 * A scenario with its quadrants, evidence and generation state, as the narrative pages show it:
 * the exercise's Step 6 studio and the older Scenarios page.
 */
class ScenarioPresenter
{
    public function __construct(protected ScenarioService $scenarios) {}

    /**
     * Scenario with its quadrants and evidence. Citations are returned as the
     * S-references the narrative uses, with the signals behind them.
     */
    public function present(Scenario $scenario, ?StructuredLlmClient $llm = null): array
    {
        $scenario->load(['axis_a.dimension', 'axis_b.dimension', 'axis_a_uncertainty.projectForces.drivingForce', 'axis_b_uncertainty.projectForces.drivingForce', 'quadrants.reviewer:id,name', 'project:id,uuid,name']);
        $evidence = $this->scenarios->storedEvidence($scenario);
        $refById = collect($scenario->input_signal_ids ?? [])->values()->mapWithKeys(fn ($id, $index) => [$id => 'S'.($index + 1)]);
        // A framework axis is a critical uncertainty: its name, and its member forces in place of a dimension.
        $axis = fn (string $letter, $low, $high) => [
            'id' => $scenario->axisUncertainty($letter)?->uuid ?? $scenario->axisForce($letter)?->uuid,
            'keyword' => $scenario->axisName($letter) ?? '(Faktor dihapus)',
            'dimension' => $scenario->axisUncertainty($letter)
                ? $scenario->axisUncertainty($letter)->projectForces->map(fn ($link) => $link->drivingForce?->keyword)->filter()->implode(', ')
                : $scenario->axisForce($letter)?->dimension?->name,
            'is_deleted' => ! $scenario->axisUncertainty($letter) && ($scenario->axisForce($letter) === null || $scenario->axisForce($letter)->trashed()),
            'low' => $low,
            'high' => $high,
        ];

        return [
            'id' => $scenario->uuid,
            'name' => $scenario->name,
            'focal_question' => $scenario->focal_question,
            'status' => $scenario->status,
            'project' => $scenario->project ? ['id' => $scenario->project->uuid, 'name' => $scenario->project->name] : null,
            'framework_status' => $scenario->framework_status,
            'axis_a' => $axis('A', $scenario->axis_a_low_label, $scenario->axis_a_high_label),
            'axis_b' => $axis('B', $scenario->axis_b_low_label, $scenario->axis_b_high_label),
            'provider' => $scenario->provider,
            'model' => $scenario->model,
            'prompt_version' => $scenario->prompt_version,
            'generated_at' => $scenario->generated_at,
            'generation' => $this->generation($scenario, $llm ?? app(StructuredLlmClient::class)),
            'evidence' => $evidence,
            'quadrants' => $scenario->quadrants->map(fn ($quadrant) => [
                'id' => $quadrant->uuid,
                'position' => $quadrant->position,
                'poles' => $scenario->poles($quadrant->position),
                'title' => $quadrant->title,
                'summary' => $quadrant->summary,
                'narrative' => $quadrant->narrative,
                'statements' => $quadrant->narrative_statements === null ? null : array_map(fn ($row) => [
                    'text' => $row['text'],
                    'kind' => $row['kind'],
                    'refs' => collect($row['signal_ids'] ?? [])->map(fn ($id) => $refById[$id] ?? null)->filter()->values()->all(),
                ], $quadrant->narrative_statements),
                'implications' => $quadrant->implications,
                'early_indicators' => $quadrant->early_indicators ?? [],
                'cited_refs' => collect($quadrant->cited_signal_ids ?? [])->map(fn ($id) => $refById[$id] ?? null)->filter()->values()->all(),
                'is_ai_generated' => $quadrant->is_ai_generated,
                'origin' => $quadrant->origin,
                'provider' => $quadrant->provider,
                'review_status' => $quadrant->review_status,
                'reviewed_by' => $quadrant->reviewer?->name,
                'reviewed_at' => $quadrant->reviewed_at,
                'stale_reason' => $quadrant->stale_since ? $quadrant->stale_reason : null,
                'edited' => $quadrant->isEdited(),
                'edited_at' => $quadrant->edited_at,
            ])->values()->all(),
        ];
    }

    /**
     * State of the last queued generation; null when none was queued. The
     * message describes a completed one.
     */
    private function generation(Scenario $scenario, StructuredLlmClient $llm): ?array
    {
        $status = $scenario->generation_status;
        if ($status === null) {
            return null;
        }

        $message = null;
        if ($status === LlmJobStatus::COMPLETED) {
            $message = $scenario->provider !== ScenarioNarrativeService::PROVIDER_TEMPLATE
                ? 'Narasi empat kuadran dibuat oleh '.$llm->label().'. Tinjau dan sunting sebelum dipakai.'
                : 'Kerangka empat kuadran dibuat tanpa AI. Lengkapi narasinya bersama tim.';
        }

        return [
            'status' => $status->value,
            'message' => $message,
            'notice' => $scenario->generation_notice,
            'error' => $scenario->generation_error,
        ];
    }
}
