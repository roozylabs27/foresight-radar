<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\ScenarioQuadrantRequest;
use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Services\Llm\StructuredLlmClient;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioPresenter;
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

    public function show(Scenario $scenario): JsonResponse
    {
        return response()->json($this->present($scenario), Response::HTTP_OK);
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

    protected function axisLabel(Scenario $scenario, string $axis): string
    {
        $force = $scenario->axisForce($axis);
        $name = $scenario->axisName($axis) ?? '(Faktor dihapus)';

        return $force?->trashed() ? $name.' (Dihapus)' : $name;
    }

    /** The scenario as both narrative pages show it (ScenarioPresenter). */
    protected function present(Scenario $scenario): array
    {
        return app(ScenarioPresenter::class)->present($scenario, $this->llm);
    }
}
