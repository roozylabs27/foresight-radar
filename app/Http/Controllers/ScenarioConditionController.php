<?php

namespace App\Http\Controllers;

use App\Models\ScenarioCondition;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Services\ConditionSuggestionService;
use App\Services\ScenarioConditionService;
use App\Services\ScenarioPlanningProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 5: conditions per scenario (roadmap R1.5). Changes need `manage-scenario` and the project
 * policy (creator, owner, admin). Quadrants and conditions must belong to the exercise's framework.
 */
class ScenarioConditionController extends Controller
{
    public function __construct(
        protected ScenarioConditionService $conditions,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Conditions', [
            'title' => 'Develop Scenarios · '.$project->name,
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
            'quadrants' => $framework ? $this->conditions->quadrants($framework) : [],
            'overlaps' => $framework ? $this->conditions->overlaps($framework) : [],
            'predetermined' => $this->conditions->predetermined($project),
            'forces' => $this->conditions->forces($project),
            'minimum' => ScenarioConditionService::MIN_PER_QUADRANT,
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'conditionStatus' => $project->condition_suggestion_status,
            'conditionError' => $project->condition_suggestion_error,
        ]);
    }

    /** Roadmap R2.5: queue AI condition drafts for the four quadrants. */
    public function suggest(Request $request, ScenarioPlanningProject $project, ConditionSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang menyusun draf kondisi. Halaman ini diperbarui otomatis.');
    }

    public function accept(Request $request, ScenarioPlanningProject $project, ScenarioCondition $condition, ConditionSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $condition);

        $suggestions->accept($condition, $request->user());

        return back()->with('success', 'Draf kondisi diterima.');
    }

    public function store(Request $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($quadrant->scenario?->project_id === $project->id, 404);

        $this->conditions->create($quadrant, $this->validated($request), $request->user());

        return back()->with('success', 'Kondisi ditambahkan.');
    }

    public function update(Request $request, ScenarioPlanningProject $project, ScenarioCondition $condition): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $condition);

        $this->conditions->update($condition, $this->validated($request), $request->user());

        return back()->with('success', 'Kondisi diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, ScenarioCondition $condition): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $condition);

        $this->conditions->delete($condition);

        return back()->with('success', 'Kondisi dihapus.');
    }

    public function move(Request $request, ScenarioPlanningProject $project, ScenarioCondition $condition): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $condition);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $this->conditions->move($condition, $validated['direction']);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(ScenarioCondition::KINDS)],
            'text' => ['required', 'string', 'max:500'],
            'axis' => ['nullable', Rule::in(['A', 'B'])],
            'force' => ['nullable', 'uuid'],
        ], [], ['kind' => 'jenis', 'text' => 'kondisi', 'axis' => 'sumbu', 'force' => 'driving force']);
    }

    private function ensureBelongs(ScenarioPlanningProject $project, ScenarioCondition $condition): void
    {
        abort_unless($condition->quadrant?->scenario?->project_id === $project->id, 404);
    }
}
