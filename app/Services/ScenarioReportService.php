<?php

namespace App\Services;

use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;

/**
 * Roadmap R3.5: one exercise as a printable report following the deck (slides 15–25). Only agreed
 * content is included: included forces with their agreed scores, approved uncertainties, accepted
 * conditions and implications, approved narratives, responses that are not rejected and active,
 * accepted indicators. Until G1–G7 have all passed the report is a draft.
 */
class ScenarioReportService
{
    public function __construct(
        protected ScenarioPlanningProjectService $projects,
        protected ProjectAssessmentService $assessments,
        protected CriticalUncertaintyService $uncertainties,
        protected ScenarioComparisonService $comparison,
        protected StrategicResponseService $responses,
        protected EarlyWarningIndicatorService $indicators,
        protected ScenarioService $scenarios,
    ) {}

    /** @return array<string, mixed> */
    public function build(ScenarioPlanningProject $project): array
    {
        $framework = $project->framework()->first();
        $gates = $this->projects->gates($project);
        $comparison = $framework ? collect($this->comparison->quadrants($framework))->keyBy('position') : collect();

        return [
            'project' => $this->projects->present($project),
            'gates' => $gates,
            'draft' => collect($gates)->contains(fn ($gate) => ! $gate['passed']),
            'forces' => collect($this->assessments->list($project))->map(fn ($row) => [
                'keyword' => $row['force']['keyword'],
                'dimension' => $row['force']['dimension'],
                'impact' => $row['impact'],
                'uncertainty' => $row['uncertainty'],
                'predetermined' => (bool) $row['is_predetermined'],
            ])->values()->all(),
            'uncertainties' => collect($this->uncertainties->list($project))->where('status', 'approved')->sortBy('axis')->map(fn ($row) => [
                'axis' => $row['axis'],
                'name' => $row['name'],
                'description' => $row['description'],
                'low' => $row['low_pole_label'],
                'high' => $row['high_pole_label'],
                'rationale' => $row['selection_rationale'],
                'forces' => array_column($row['forces'], 'keyword'),
            ])->values()->all(),
            'framework' => $framework ? [
                'name' => $framework->name,
                'approved' => $framework->isFrameworkApproved(),
                'axes' => [
                    ['axis' => 'A', 'name' => $framework->axisName('A'), 'low' => $framework->axis_a_low_label, 'high' => $framework->axis_a_high_label],
                    ['axis' => 'B', 'name' => $framework->axisName('B'), 'low' => $framework->axis_b_low_label, 'high' => $framework->axis_b_high_label],
                ],
            ] : null,
            'quadrants' => $framework ? $framework->quadrants()->get()->map(fn (ScenarioQuadrant $quadrant) => $comparison[$quadrant->position] + [
                // Only an approved narrative (G6) is agreed content.
                'narrative' => $quadrant->review_status === 'approved' ? $quadrant->narrative : null,
            ])->values()->all() : [],
            'evidence' => $framework ? collect($this->scenarios->storedEvidence($framework))->map(fn ($row) => [
                'ref' => $row['ref'],
                'title' => $row['title'],
                'source' => $row['source_title'] ?? null,
                'url' => $row['source_url'] ?? null,
            ])->values()->all() : [],
            'responses' => collect($this->responses->list($project))->where('status', '!=', 'rejected')->map(fn ($row) => [
                'title' => $row['title'],
                'description' => $row['description'],
                'initiatives' => $row['initiatives'],
                'owner' => $row['owner']['name'] ?? null,
                'status' => $row['status'],
                'robustness' => $row['robustness'],
                'fit' => $row['fit'],
            ])->values()->all(),
            'indicators' => collect($this->indicators->list($project))->where('status', 'active')->where('review_status', 'accepted')->map(fn ($row) => [
                'name' => $row['name'],
                'metric' => $row['metric'],
                'threshold_rule' => $row['threshold_rule'],
                'owner' => $row['owner']['name'] ?? null,
                'directions' => (object) collect((array) $row['directions'])->map(fn ($direction) => $direction['direction'])->all(),
                'discriminates' => $row['discriminates'],
            ])->values()->all(),
            'decisions' => array_reverse($this->projects->decisions($project, 200)),
            'generated_at' => now()->toISOString(),
        ];
    }
}
