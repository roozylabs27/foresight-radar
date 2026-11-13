<?php

namespace App\Http\Controllers;

use App\Http\Requests\CriticalUncertaintyRequest;
use App\Models\CriticalUncertainty;
use App\Models\ScenarioPlanningProject;
use App\Services\CriticalUncertaintyService;
use App\Services\ScenarioPlanningProjectService;
use App\Support\DecisionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 3, second half: critical uncertainties and gate G4 (roadmap R0.4).
 * Changes need `manage-scenario` and the project policy (creator, owner, admin).
 */
class CriticalUncertaintyController extends Controller
{
    public function __construct(
        protected CriticalUncertaintyService $uncertainties,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        return Inertia::render('ScenarioPlanning/Uncertainties', [
            'title' => 'Critical Uncertainties · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'uncertainties' => $this->uncertainties->list($project),
            'candidates' => $this->uncertainties->candidates($project),
            'pairs' => $this->uncertainties->pairs($project),
            'threshold' => ScenarioPlanningProjectService::HIGH_SCORE,
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function store(CriticalUncertaintyRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->uncertainties->create($project, $request->validated(), $request->user());

        return back()->with('success', 'Usulan critical uncertainty disimpan.');
    }

    public function update(CriticalUncertaintyRequest $request, ScenarioPlanningProject $project, CriticalUncertainty $uncertainty): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->uncertainties->update($uncertainty, $request->validated(), $request->user());

        return back()->with('success', 'Usulan critical uncertainty diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, CriticalUncertainty $uncertainty): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->uncertainties->delete($uncertainty);

        return back()->with('success', 'Usulan critical uncertainty dihapus.');
    }

    public function approve(Request $request, ScenarioPlanningProject $project, CriticalUncertainty $uncertainty): RedirectResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request, required: $request->boolean('acknowledge_dependency'));

        $validated = $request->validate([
            'axis' => ['required', Rule::in(CriticalUncertainty::AXES)],
            'acknowledge_dependency' => ['boolean'],
        ]);

        $this->uncertainties->approve($uncertainty, $validated['axis'], $request->boolean('acknowledge_dependency'), $request->user());

        return back()->with('success', $project->fresh()->uncertaintiesApproved()
            ? 'Dua critical uncertainty disetujui. Langkah 4 (Scenario Framework) terbuka.'
            : "Disetujui sebagai sumbu {$validated['axis']}.");
    }

    public function withdraw(Request $request, ScenarioPlanningProject $project, CriticalUncertainty $uncertainty): RedirectResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request);

        $this->uncertainties->withdraw($uncertainty, $request->user());

        return back()->with('success', 'Persetujuan dibatalkan. Usulan bisa diubah lagi.');
    }
}
