<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioFrameworkRequest;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Services\FrameworkSuggestionService;
use App\Services\ScenarioFrameworkService;
use App\Services\ScenarioPlanningProjectService;
use App\Support\DecisionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 4: the exercise's scenario framework and gate G5 (roadmap R0.5).
 * Changes need `manage-scenario` and the project policy (creator, owner, admin).
 */
class ScenarioFrameworkController extends Controller
{
    public function __construct(
        protected ScenarioFrameworkService $frameworks,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        return Inertia::render('ScenarioPlanning/Framework', [
            'title' => 'Scenario Framework · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'framework' => $this->frameworks->present($project),
            'axesApproved' => $project->uncertaintiesApproved(),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
        ]);
    }

    public function store(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate(['name' => ['nullable', 'string', 'max:255']]);
        $this->frameworks->create($project, array_filter($validated), $request->user());

        return back()->with('success', 'Kerangka dibuat dengan empat kuadran. Beri nama dan ringkasan tiap kuadran.');
    }

    public function update(ScenarioFrameworkRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->frameworks->updatePoles($this->framework($project), $request->validated(), $request->user());

        return back()->with('success', 'Kutub sumbu diperbarui.');
    }

    public function updateQuadrant(Request $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($quadrant->scenario_id === $this->framework($project)->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:200'],
        ], [], ['title' => 'nama kuadran', 'summary' => 'ringkasan']);
        $this->frameworks->updateQuadrant($quadrant, $validated, $request->user());

        return back()->with('success', 'Kuadran disimpan.');
    }

    /** Roadmap R2.5: queue AI pole labels and quadrant names for the draft framework. */
    public function suggest(Request $request, ScenarioPlanningProject $project, FrameworkSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang menyiapkan saran kutub dan nama kuadran. Halaman ini diperbarui otomatis.');
    }

    public function acceptSuggestion(Request $request, ScenarioPlanningProject $project, FrameworkSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->accept($project, $request->user());

        return back()->with('success', 'Saran AI dipakai untuk kutub dan nama kuadran.');
    }

    public function rejectSuggestion(ScenarioPlanningProject $project, FrameworkSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->reject($project);

        return back()->with('success', 'Saran AI dibuang.');
    }

    public function approve(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request);

        $this->frameworks->approve($this->framework($project), $request->user());

        return back()->with('success', 'Kerangka disetujui. Langkah 5–9 terbuka.');
    }

    public function reopen(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request);

        $this->frameworks->reopen($this->framework($project), $request->user());

        return back()->with('success', 'Kerangka dibuka kembali. Setujui lagi setelah selesai mengubah.');
    }

    private function framework(ScenarioPlanningProject $project): Scenario
    {
        return $project->framework()->firstOrFail();
    }
}
