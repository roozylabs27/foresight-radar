<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioPlanningProjectRequest;
use App\Models\ProjectParticipant;
use App\Models\ScenarioPlanningProject;
use App\Services\FocalIssueSuggestionService;
use App\Services\ScenarioPlanningProjectService;
use App\Services\StalenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scenario planning exercises (roadmap R0.1). The project workspace grows a
 * screen per method step in later roadmap items.
 */
class ScenarioPlanningProjectController extends Controller
{
    public function __construct(
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request): Response
    {
        $projects = ScenarioPlanningProject::with('owner:id,uuid,name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($project) => $this->projects->present($project));

        return Inertia::render('ScenarioPlanning/Index', [
            'title' => 'Scenario Planning',
            'projects' => $projects,
            'canManage' => Gate::allows('manage-scenario'),
            'owners' => $this->projects->owners($request->user()),
        ]);
    }

    public function show(Request $request, ScenarioPlanningProject $project): Response
    {
        $steps = $this->projects->steps($project);

        return Inertia::render('ScenarioPlanning/Show', [
            'title' => $project->name,
            'project' => $this->projects->present($project),
            'steps' => $steps,
            'nextAction' => $this->projects->nextAction($project, $steps),
            'lastGate' => $this->projects->lastGate($project),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'owners' => $this->projects->owners($request->user()),
            'participants' => $this->projects->participants($project),
            'participantRoles' => ProjectParticipant::ROLES,
            'decisions' => $this->projects->decisions($project),
            'focalSuggestion' => $project->focal_suggestion,
            'focalStatus' => $project->focal_suggestion_status,
            'focalError' => $project->focal_suggestion_error,
        ]);
    }

    public function store(ScenarioPlanningProjectRequest $request): RedirectResponse
    {
        $project = $this->projects->create($request->validated(), $request->user());

        return to_route('scenario-planning.show', $project)->with('success', 'Latihan scenario planning dibuat. Konfirmasi focal issue untuk membuka langkah berikutnya.');
    }

    public function update(ScenarioPlanningProjectRequest $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $reconfirm = $this->projects->update($project, $request->validated(), $request->user());

        return back()->with('success', $reconfirm
            ? 'Focal issue diubah setelah dikonfirmasi. Konfirmasi ulang sebelum melanjutkan langkah berikutnya.'
            : 'Latihan disimpan.');
    }

    public function confirm(Request $request, ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('confirm', $project);

        $this->projects->confirm($project, $request->user());

        return back()->with('success', 'Focal issue dikonfirmasi. Langkah 2–9 sekarang terbuka.');
    }

    /** Roadmap R3.1: queue an AI critique of the focal question. */
    public function suggestFocal(Request $request, ScenarioPlanningProject $project, FocalIssueSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang meninjau pertanyaan fokus. Halaman ini diperbarui otomatis.');
    }

    public function rejectFocal(ScenarioPlanningProject $project, FocalIssueSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->reject($project);

        return back()->with('success', 'Saran AI dibuang.');
    }

    /** "Keep" flagged work after an upstream change (roadmap R1.10). */
    public function acknowledgeStale(Request $request, ScenarioPlanningProject $project, StalenessService $staleness): RedirectResponse
    {
        $this->authorize('update', $project);

        $staleness->acknowledge($project, $request->user());

        return back()->with('success', 'Ditandai sudah ditinjau. Pekerjaan dipertahankan apa adanya.');
    }

    public function destroy(ScenarioPlanningProject $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return to_route('scenario-planning.index')->with('success', 'Latihan dihapus.');
    }
}
