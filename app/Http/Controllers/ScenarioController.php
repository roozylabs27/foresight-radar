<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\ScenarioQuadrantRequest;
use App\Http\Requests\ScenarioRequest;
use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Services\Llm\StructuredLlmClient;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioPresenter;
use App\Services\ScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ScenarioController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected ScenarioService $scenarios,
        protected ScenarioNarrativeService $narratives,
        protected StructuredLlmClient $llm,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Report/Scenarios', [
            'title' => 'Scenarios',
            'canManage' => Gate::allows('manage-scenario'),
            'llm' => [
                'available' => $this->llm->isAvailable(),
                'provider' => $this->llm->isAvailable() ? $this->llm->provider() : null,
                'label' => $this->llm->isAvailable() ? $this->llm->label() : null,
                'model' => $this->llm->isAvailable() ? $this->llm->model() : null,
            ],
        ]);
    }

    public function fetch(): JsonResponse
    {
        try {
            $scenarios = Scenario::with(['axis_a:id,uuid,keyword,deleted_at', 'axis_b:id,uuid,keyword,deleted_at', 'axis_a_uncertainty:id,name', 'axis_b_uncertainty:id,name', 'project:id,uuid,name'])
                ->withCount('quadrants')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn ($scenario) => [
                    'id' => $scenario->uuid,
                    'name' => $scenario->name,
                    'status' => $scenario->status,
                    'provider' => $scenario->provider,
                    'generation_status' => $scenario->generation_status?->value,
                    'axis_a' => $this->axisLabel($scenario, 'A'),
                    'axis_b' => $this->axisLabel($scenario, 'B'),
                    'project' => $scenario->project ? ['id' => $scenario->project->uuid, 'name' => $scenario->project->name] : null,
                    'quadrants_count' => $scenario->quadrants_count,
                    'updated_at' => $scenario->updated_at,
                ]);

            return response()->json($scenarios, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'fetching scenarios');
        }
    }

    public function suggestAxes(): JsonResponse
    {
        return response()->json($this->scenarios->suggestAxes(), Response::HTTP_OK);
    }

    public function show(Scenario $scenario): JsonResponse
    {
        return response()->json($this->present($scenario), Response::HTTP_OK);
    }

    public function store(ScenarioRequest $request): JsonResponse
    {
        $validated = $request->validated();
        [$axisA, $axisB] = $this->scenarios->resolveAxes($validated['axis_a'], $validated['axis_b']);

        $scenario = Scenario::create($this->attributes($validated, $axisA->id, $axisB->id) + [
            'status' => 'DRAFT',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Skenario dibuat.',
            'scenario' => $this->present($scenario),
        ], Response::HTTP_CREATED);
    }

    public function update(ScenarioRequest $request, Scenario $scenario): JsonResponse
    {
        $this->authorize('update', $scenario);
        $this->refuseFramework($scenario);

        $validated = $request->validated();
        [$axisA, $axisB] = $this->scenarios->resolveAxes($validated['axis_a'], $validated['axis_b']);

        $scenario->update($this->attributes($validated, $axisA->id, $axisB->id) + [
            'status' => $validated['status'] ?? $scenario->status,
            'updated_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'Skenario diperbarui.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    public function destroy(Scenario $scenario): JsonResponse
    {
        $this->authorize('delete', $scenario);
        if ($scenario->isFrameworkApproved()) {
            throw ValidationException::withMessages(['framework' => 'Kerangka yang sudah disetujui tidak bisa dihapus. Buka kembali kerangkanya di latihan scenario planning.']);
        }

        $scenario->delete();

        return response()->json(['message' => 'Skenario dihapus.'], Response::HTTP_OK);
    }

    public function updateQuadrant(ScenarioQuadrantRequest $request, Scenario $scenario, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $scenario);
        abort_unless($quadrant->scenario_id === $scenario->id, Response::HTTP_NOT_FOUND);

        $this->narratives->updateQuadrant($quadrant, $request->validated(), (int) Auth::id());

        return response()->json(['message' => 'Kuadran disimpan.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    /** Review state of one quadrant narrative; `approved` is gate G6 (ScenarioNarrativeService::reviewQuadrant). */
    public function reviewQuadrant(Request $request, Scenario $scenario, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $scenario);
        abort_unless($quadrant->scenario_id === $scenario->id, Response::HTTP_NOT_FOUND);

        $status = $request->validate(['status' => ['required', Rule::in(ScenarioQuadrant::REVIEW_STATUSES)]])['status'];
        $this->narratives->reviewQuadrant($quadrant, $status, (int) Auth::id());

        return response()->json(['message' => $status === 'approved' ? 'Narasi disetujui.' : 'Status tinjauan diperbarui.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    /**
     * Queue the narrative. The client polls `show` until `generation.status`
     * is completed or failed.
     */
    public function generate(Request $request, Scenario $scenario): JsonResponse
    {
        $this->authorize('update', $scenario);

        $scenario = $this->narratives->queue($scenario, $request->boolean('overwrite_edited'), Auth::id());

        return response()->json([
            'message' => 'Narasi sedang dibuat di latar belakang.',
            'scenario' => $this->present($scenario),
        ], Response::HTTP_ACCEPTED);
    }

    /** Axes and poles of an exercise framework are edited in the exercise (step 4), not here. */
    protected function refuseFramework(Scenario $scenario): void
    {
        if ($scenario->isFramework()) {
            throw ValidationException::withMessages(['framework' => 'Sumbu dan kutub kerangka ini diatur di latihan scenario planning (langkah 4).']);
        }
    }

    protected function axisLabel(Scenario $scenario, string $axis): string
    {
        $force = $scenario->axisForce($axis);
        $name = $scenario->axisName($axis) ?? '(Faktor dihapus)';

        return $force?->trashed() ? $name.' (Dihapus)' : $name;
    }

    protected function attributes(array $validated, int $axisA, int $axisB): array
    {
        return [
            'name' => $validated['name'],
            'focal_question' => $validated['focal_question'] ?? null,
            'axis_a_force_id' => $axisA,
            'axis_b_force_id' => $axisB,
            'axis_a_low_label' => $validated['axis_a_low_label'],
            'axis_a_high_label' => $validated['axis_a_high_label'],
            'axis_b_low_label' => $validated['axis_b_low_label'],
            'axis_b_high_label' => $validated['axis_b_high_label'],
        ];
    }

    /** The scenario as both narrative pages show it (ScenarioPresenter). */
    protected function present(Scenario $scenario): array
    {
        return app(ScenarioPresenter::class)->present($scenario, $this->llm);
    }
}
