<?php

namespace App\Http\Controllers;

use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Services\ScenarioComparisonService;
use App\Services\ScenarioConsistencyService;
use App\Services\ScenarioPlanningProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roadmap R2.8: read-only comparison of the four futures (summary, conditions, implications,
 * responses, indicators). Needs `view-scenario`.
 */
class ScenarioComparisonController extends Controller
{
    public function __construct(
        protected ScenarioComparisonService $comparison,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Compare', [
            'title' => 'Bandingkan skenario · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'framework' => $framework ? [
                'id' => $framework->uuid,
                'name' => $framework->name,
                'approved' => $framework->isFrameworkApproved(),
                'axes' => [
                    ['axis' => 'A', 'name' => $framework->axisName('A'), 'low' => $framework->axis_a_low_label, 'high' => $framework->axis_a_high_label],
                    ['axis' => 'B', 'name' => $framework->axisName('B'), 'low' => $framework->axis_b_low_label, 'high' => $framework->axis_b_high_label],
                ],
            ] : null,
            'quadrants' => $framework ? $this->comparison->quadrants($framework) : [],
            'responses' => $framework ? $this->comparison->responses($project) : [],
            'indicators' => $framework ? $this->comparison->indicators($project) : [],
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    /** Slice 2: the coordinator records whether a quadrant is internally consistent. */
    public function consistency(Request $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant, ScenarioConsistencyService $consistency): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($quadrant->scenario?->project_id === $project->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ScenarioConsistencyService::STATUSES)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $consistency->signOff($quadrant, $validated, $request->user());

        return back()->with('success', $validated['status'] === 'consistent' ? 'Kuadran ditandai konsisten.' : 'Catatan ketidakkonsistenan disimpan.');
    }
}
