<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioQuadrantRequest;
use App\Models\Scenario;
use App\Models\ScenarioPlanningProject;
use App\Models\ScenarioQuadrant;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioPlanningProjectService;
use App\Services\ScenarioPresenter;
use App\Support\DecisionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Step 6 inside the exercise (audit U3, AUD-01): the narratives of the exercise's framework.
 * Changes need `manage-scenario` and the exercise policy (creator, owner, admin); a quadrant must
 * belong to this exercise's framework. Generation, edits and reviews share ScenarioNarrativeService
 * with the older Scenarios page.
 */
class ExerciseNarrativeController extends Controller
{
    public function __construct(
        protected ScenarioNarrativeService $narratives,
        protected ScenarioPresenter $presenter,
        protected ScenarioPlanningProjectService $projects,
    ) {}

    public function index(Request $request, ScenarioPlanningProject $project): Response
    {
        $framework = $project->framework()->first();
        $llm = $this->narratives->clientFor($request->user()->id);

        return Inertia::render('ScenarioPlanning/Narratives', [
            'title' => 'Narratives · '.$project->name,
            'project' => $this->projects->present($project),
            'steps' => $this->projects->steps($project),
            'framework' => $framework ? $this->presenter->present($framework, $llm) : null,
            'revisions' => $framework ? $this->revisions($framework) : (object) [],
            'canManage' => Gate::allows('manage-scenario') && $request->user()->can('update', $project),
            'llm' => ['available' => $llm->isAvailable(), 'label' => $llm->isAvailable() ? $llm->label() : null, 'model' => $llm->isAvailable() ? $llm->model() : null],
        ]);
    }

    /** The framework as the page shows it; polled while a generation runs. */
    public function state(ScenarioPlanningProject $project): JsonResponse
    {
        return response()->json($this->presenter->present($this->framework($project)));
    }

    public function generate(Request $request, ScenarioPlanningProject $project): JsonResponse
    {
        $this->authorize('update', $project);

        $scenario = $this->narratives->queue($this->framework($project), $request->boolean('overwrite_edited'), $request->user()->id);

        return response()->json(['message' => 'Narasi sedang dibuat di latar belakang.', 'scenario' => $this->presenter->present($scenario)], HttpResponse::HTTP_ACCEPTED);
    }

    public function updateQuadrant(ScenarioQuadrantRequest $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $project);
        $framework = $this->owned($project, $quadrant);

        $this->narratives->updateQuadrant($quadrant, $request->validated(), $request->user()->id);

        return response()->json(['message' => 'Kuadran disimpan.', 'scenario' => $this->presenter->present($framework)]);
    }

    public function reviewQuadrant(Request $request, ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $project);
        DecisionContext::fromRequest($request);
        $framework = $this->owned($project, $quadrant);
        $status = $request->validate(['status' => ['required', Rule::in(ScenarioQuadrant::REVIEW_STATUSES)]])['status'];

        $this->narratives->reviewQuadrant($quadrant, $status, $request->user()->id);

        return response()->json(['message' => $status === 'approved' ? 'Narasi disetujui.' : 'Status tinjauan diperbarui.', 'scenario' => $this->presenter->present($framework)]);
    }

    /** @throws ValidationException when the exercise has no framework yet */
    private function framework(ScenarioPlanningProject $project): Scenario
    {
        return $project->framework()->first()
            ?? throw ValidationException::withMessages(['framework' => 'Buat dan setujui kerangka skenario (langkah 4) dulu.']);
    }

    private function owned(ScenarioPlanningProject $project, ScenarioQuadrant $quadrant): Scenario
    {
        $framework = $project->framework()->first();
        abort_unless($framework && $quadrant->scenario_id === $framework->id, HttpResponse::HTTP_NOT_FOUND);

        return $framework;
    }

    /**
     * Earlier versions per quadrant uuid, newest first.
     *
     * @return array<string, array<int, array<string, mixed>>>|object
     */
    private function revisions(Scenario $framework): array|object
    {
        $rows = $framework->quadrants()->with(['revisions' => fn ($query) => $query->orderByDesc('id')->with('user:id,name')])->get()
            ->mapWithKeys(fn (ScenarioQuadrant $quadrant) => [$quadrant->uuid => $quadrant->revisions->map(fn ($revision) => [
                'id' => $revision->uuid,
                'reason' => $revision->reason,
                'title' => $revision->title,
                'narrative' => $revision->narrative,
                'origin' => $revision->origin,
                'review_status' => $revision->review_status,
                'user' => $revision->user?->name,
                'created_at' => $revision->created_at?->toISOString(),
            ])->values()->all()])
            ->filter(fn ($list) => $list !== []);

        return $rows->isEmpty() ? (object) [] : $rows->all();
    }
}
