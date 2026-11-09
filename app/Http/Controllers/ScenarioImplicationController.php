<?php

namespace App\Http\Controllers;

use App\Models\ScenarioImplication;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Services\ImplicationSuggestionService;
use App\Services\ScenarioImplicationService;
use App\Services\ScenarioPlanningProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Step 7: challenges and opportunities per scenario (roadmap R1.7). Changes need `manage-scenario`
 * and the project policy (creator, owner, admin). Items must belong to the exercise's framework.
 */
class ScenarioImplicationController extends Controller
{
    public function __construct(
        protected ScenarioImplicationService $implications,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();

        return Inertia::render('ScenarioPlanning/Implications', [
            'title' => 'Challenges & Opportunities · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'framework' => $framework ? ['id' => $framework->uuid, 'name' => $framework->name, 'approved' => $framework->isFrameworkApproved()] : null,
            'quadrants' => $framework ? $this->implications->quadrants($framework) : [],
            'forces' => $this->implications->forces($project),
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'implicationStatus' => $project->implication_suggestion_status,
            'implicationError' => $project->implication_suggestion_error,
        ]);
    }

    /** Roadmap R2.6: queue AI drafts of opportunities and challenges. */
    public function suggest(Request $request, ScenarioPlanningProject $project, ImplicationSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $suggestions->request($project, $request->user());

        return back()->with('success', 'AI sedang menyusun draf peluang dan tantangan. Halaman ini diperbarui otomatis.');
    }

    public function accept(Request $request, ScenarioPlanningProject $project, ScenarioImplication $implication, ImplicationSuggestionService $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $implication);

        $suggestions->accept($implication, $request->user());

        return back()->with('success', 'Draf diterima.');
    }

    public function store(Request $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($quadrant->scenario?->project_id === $project->id, 404);

        $this->implications->create($quadrant, $this->validated($request), $request->user());

        return back()->with('success', 'Item disimpan.');
    }

    public function update(Request $request, ScenarioPlanningProject $project, ScenarioImplication $implication): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $implication);

        $this->implications->update($implication, $this->validated($request), $request->user());

        return back()->with('success', 'Item diperbarui.');
    }

    public function destroy(ScenarioPlanningProject $project, ScenarioImplication $implication): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongs($project, $implication);

        $this->implications->delete($implication);

        return back()->with('success', 'Item dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(ScenarioImplication::TYPES)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'impact_level' => ['nullable', Rule::in(ScenarioImplication::IMPACT_LEVELS)],
            'priority' => ['nullable', 'integer', 'between:1,5'],
            'forces' => ['nullable', 'array', 'max:20'],
            'forces.*' => ['uuid'],
            'conditions' => ['nullable', 'array', 'max:20'],
            'conditions.*' => ['uuid'],
        ], [], ['type' => 'jenis', 'title' => 'judul', 'description' => 'deskripsi', 'impact_level' => 'tingkat dampak', 'priority' => 'prioritas']);
    }

    private function ensureBelongs(ScenarioPlanningProject $project, ScenarioImplication $implication): void
    {
        abort_unless($implication->quadrant?->scenario?->project_id === $project->id, 404);
    }
}
