<?php

namespace App\Http\Controllers;

use App\Http\Requests\StrategicResponseRequest;
use App\Models\ScenarioPlanningProject;
use App\Models\StrategicResponse;
use App\Services\ScenarioPlanningProjectService;
use App\Services\StrategicResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 8: strategic responses and gate G7 (roadmap R1.8). Changes need `manage-scenario` and the
 * project policy (creator, owner, admin).
 */
class StrategicResponseController extends Controller
{
    public function __construct(
        protected StrategicResponseService $responses,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Responses', [
            'title' => 'Strategic Response · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'frameworkApproved' => $framework?->isFrameworkApproved() ?? false,
            'quadrants' => $framework ? $framework->quadrants()->get()->map(fn ($quadrant) => [
                'position' => $quadrant->position, 'title' => $quadrant->title, 'poles' => $framework->poles($quadrant->position),
            ])->values()->all() : [],
            'responses' => $this->responses->list($project),
            'implications' => $framework ? $this->responses->implicationOptions($framework) : [],
            'owners' => $this->projects->owners($request->user()),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function store(StrategicResponseRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->responses->create($project, $request->validated(), $request->user());

        return back()->with('success', 'Respons strategis disimpan sebagai usulan.');
    }

    public function update(StrategicResponseRequest $request, ScenarioPlanningProject $project, StrategicResponse $response): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->responses->update($response, $request->validated(), $request->user());

        return back()->with('success', 'Respons strategis diperbarui dan kembali menjadi usulan.');
    }

    public function destroy(ScenarioPlanningProject $project, StrategicResponse $response): RedirectResponse
    {
        $this->authorize('update', $project);

        $response->delete();

        return back()->with('success', 'Respons strategis dihapus.');
    }

    public function approve(Request $request, ScenarioPlanningProject $project, StrategicResponse $response): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->responses->approve($response, $request->user());

        return back()->with('success', 'Respons strategis disetujui.');
    }

    public function reject(Request $request, ScenarioPlanningProject $project, StrategicResponse $response): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->responses->reject($response, $request->user());

        return back()->with('success', 'Respons strategis ditolak.');
    }
}
