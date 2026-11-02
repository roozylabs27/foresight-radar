<?php

namespace App\Http\Controllers;

use App\Http\Requests\EarlyWarningIndicatorRequest;
use App\Models\EarlyWarningIndicator;
use App\Models\ScenarioPlanningProject;
use App\Services\EarlyWarningIndicatorService;
use App\Services\ScenarioPlanningProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 9: early warning indicator register (roadmap R1.9). Changes need `manage-scenario` and the
 * project policy (creator, owner, admin).
 */
class EarlyWarningIndicatorController extends Controller
{
    public function __construct(
        protected EarlyWarningIndicatorService $indicators,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Indicators', [
            'title' => 'Early Warning · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'frameworkApproved' => $framework?->isFrameworkApproved() ?? false,
            'quadrants' => $framework ? $this->indicators->quadrants($framework) : [],
            'indicators' => $this->indicators->list($project),
            'uncertainties' => $project->criticalUncertainties()->orderBy('name')->get(['uuid', 'name'])->map(fn ($item) => ['id' => $item->uuid, 'name' => $item->name])->all(),
            'forces' => $project->projectForces()->where('inclusion_status', 'included')->with('drivingForce')->get()
                ->map(fn ($link) => ['id' => $link->uuid, 'keyword' => $link->drivingForce?->keyword])->sortBy('keyword')->values()->all(),
            'owners' => $this->projects->owners($request->user()),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function store(EarlyWarningIndicatorRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->create($project, $request->validated(), $request->user());

        return back()->with('success', 'Indikator disimpan.');
    }

    public function update(EarlyWarningIndicatorRequest $request, ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->update($indicator, $request->validated(), $request->user());

        return back()->with('success', 'Indikator diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, EarlyWarningIndicator $indicator): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->indicators->delete($indicator);

        return back()->with('success', 'Indikator dihapus.');
    }
}
