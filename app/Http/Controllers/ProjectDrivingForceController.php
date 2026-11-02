<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectDrivingForceRequest;
use App\Models\ProjectDrivingForce;
use App\Models\ScenarioPlanningProject;
use App\Services\ProjectDrivingForceService;
use App\Services\ScenarioPlanningProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 2 of an exercise: its driving force list (roadmap R0.2). Changes need
 * `manage-scenario` and the project policy (creator, owner, admin roles).
 */
class ProjectDrivingForceController extends Controller
{
    public function __construct(
        protected ProjectDrivingForceService $forces,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        return Inertia::render('ScenarioPlanning/DrivingForces', [
            'title' => 'Driving Forces · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'forces' => $this->forces->list($project),
            'library' => $this->forces->library($project),
            'coverage' => $this->forces->coverage($project),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function store(ProjectDrivingForceRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $added = $this->forces->add($project, $request->validated('forces'), $request->user());

        return back()->with('success', $added > 0 ? "{$added} driving force ditambahkan ke latihan." : 'Driving force tersebut sudah ada di latihan.');
    }

    public function update(ProjectDrivingForceRequest $request, ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        $this->authorize('update', $project);

        $withdrawn = $this->forces->update($projectForce, $request->validated());

        return back()->with('success', $withdrawn
            ? 'Disimpan. Daftar driving force berubah, jadi kesepakatan daftar perlu diulang.'
            : 'Disimpan.');
    }

    public function destroy(ScenarioPlanningProject $project, ProjectDrivingForce $projectForce): RedirectResponse
    {
        $this->authorize('update', $project);

        $withdrawn = $this->forces->remove($projectForce);

        return back()->with('success', $withdrawn
            ? 'Driving force dikeluarkan dari latihan. Kesepakatan daftar perlu diulang.'
            : 'Driving force dikeluarkan dari latihan.');
    }

    public function confirm(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->forces->confirm($project, $request->user());

        return back()->with('success', 'Daftar driving force disepakati. Langkah 3 terbuka.');
    }
}
