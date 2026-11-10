<?php

namespace App\Http\Controllers;

use App\Models\BackcastingMilestone;
use App\Models\ScenarioPlanningProject;
use App\Services\BackcastingService;
use App\Services\ScenarioPlanningProjectService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roadmap R4.1: preferred future and backcasting milestones (deck slides 2 and 26). Anyone who may
 * view the exercise reads the page; changes need `manage-scenario` and the project policy.
 */
class BackcastingController extends Controller
{
    public function __construct(
        protected BackcastingService $backcasting,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Backcasting', [
            'title' => 'Backcasting · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'frameworkApproved' => $framework?->isFrameworkApproved() ?? false,
            'quadrants' => $framework ? $framework->quadrants()->get()->map(fn ($quadrant) => [
                'id' => $quadrant->uuid, 'position' => $quadrant->position, 'title' => $quadrant->title, 'summary' => $quadrant->summary,
            ])->values()->all() : [],
            'owners' => $this->projects->owners($request->user()),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ] + $this->backcasting->present($project) + $this->backcasting->options($project));
    }

    public function prefer(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $validated = $request->validate([
            'quadrant' => ['required', 'uuid'],
            'rationale' => ['required', 'string', 'max:2000'],
        ], [], ['quadrant' => 'skenario', 'rationale' => 'alasan']);

        $this->backcasting->choose($project, $validated['quadrant'], $validated['rationale'], $request->user());

        return back()->with('success', 'Masa depan pilihan disimpan.');
    }

    public function clear(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->backcasting->clear($project, $request->user());

        return back()->with('success', 'Masa depan pilihan dibatalkan. Milestone tetap disimpan.');
    }

    public function store(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->backcasting->save($project, $this->validated($request, $project), $request->user());

        return back()->with('success', 'Milestone ditambahkan.');
    }

    public function update(Request $request, ScenarioPlanningProject $project, BackcastingMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->backcasting->save($project, $this->validated($request, $project), $request->user(), $milestone);

        return back()->with('success', 'Milestone disimpan.');
    }

    public function destroy(Request $request, ScenarioPlanningProject $project, BackcastingMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $project);

        $milestone->delete();

        return back()->with('success', 'Milestone dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ScenarioPlanningProject $project): array
    {
        return $request->validate([
            'year' => ['required', 'integer', 'min:'.now()->year, 'max:'.$project->target_year],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(BackcastingMilestone::STATUSES)],
            'owner' => ['nullable', 'uuid', TenantRule::exists('users', 'uuid')],
            'response' => ['nullable', 'uuid'],
            'indicator' => ['nullable', 'uuid'],
        ], ['year.min' => 'Milestone dimulai paling awal tahun ini.', 'year.max' => 'Milestone paling lambat tahun target latihan.'], [
            'year' => 'tahun', 'title' => 'milestone', 'description' => 'keterangan', 'owner' => 'penanggung jawab',
            'response' => 'respons strategis', 'indicator' => 'indikator',
        ]);
    }
}
