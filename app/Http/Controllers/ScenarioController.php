<?php

namespace App\Http\Controllers;

use App\Enums\LlmJobStatus;
use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\ScenarioQuadrantRequest;
use App\Http\Requests\ScenarioRequest;
use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Services\Llm\StructuredLlmClient;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            $scenarios = Scenario::with(['axis_a:id,uuid,keyword,deleted_at', 'axis_b:id,uuid,keyword,deleted_at'])
                ->withCount('quadrants')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn ($scenario) => [
                    'id' => $scenario->uuid,
                    'name' => $scenario->name,
                    'status' => $scenario->status,
                    'provider' => $scenario->provider,
                    'generation_status' => $scenario->generation_status?->value,
                    'axis_a' => $scenario->axis_a ? ($scenario->axis_a->trashed() ? $scenario->axis_a->keyword.' (Dihapus)' : $scenario->axis_a->keyword) : '(Faktor dihapus)',
                    'axis_b' => $scenario->axis_b ? ($scenario->axis_b->trashed() ? $scenario->axis_b->keyword.' (Dihapus)' : $scenario->axis_b->keyword) : '(Faktor dihapus)',
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
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Skenario dibuat.',
            'scenario' => $this->present($scenario),
        ], Response::HTTP_CREATED);
    }

    public function update(ScenarioRequest $request, Scenario $scenario): JsonResponse
    {
        $this->authorize('update', $scenario);

        $validated = $request->validated();
        [$axisA, $axisB] = $this->scenarios->resolveAxes($validated['axis_a'], $validated['axis_b']);

        $scenario->update($this->attributes($validated, $axisA->id, $axisB->id) + [
            'status' => $validated['status'] ?? $scenario->status,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Skenario diperbarui.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    public function destroy(Scenario $scenario): JsonResponse
    {
        $this->authorize('delete', $scenario);

        $scenario->delete();

        return response()->json(['message' => 'Skenario dihapus.'], Response::HTTP_OK);
    }

    public function updateQuadrant(ScenarioQuadrantRequest $request, Scenario $scenario, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $scenario);

        abort_unless($quadrant->scenario_id === $scenario->id, Response::HTTP_NOT_FOUND);

        $quadrant->update($request->validated() + [
            'early_indicators' => $request->validated('early_indicators', []),
            'edited_by' => auth()->id(),
            'edited_at' => now(),
        ]);

        return response()->json(['message' => 'Kuadran disimpan.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    /**
     * Queue the narrative. The client polls `show` until `generation.status`
     * is completed or failed.
     */
    public function generate(Request $request, Scenario $scenario): JsonResponse
    {
        $this->authorize('update', $scenario);

        $scenario = $this->narratives->queue($scenario, $request->boolean('overwrite_edited'), auth()->id());

        return response()->json([
            'message' => 'Narasi sedang dibuat di latar belakang.',
            'scenario' => $this->present($scenario),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * State of the last queued generation; null when none was queued. The
     * message describes a completed one.
     */
    protected function generation(Scenario $scenario): ?array
    {
        $status = $scenario->generation_status;
        if ($status === null) {
            return null;
        }

        $message = null;
        if ($status === LlmJobStatus::COMPLETED) {
            $message = $scenario->provider !== ScenarioNarrativeService::PROVIDER_TEMPLATE
                ? 'Narasi empat kuadran dibuat oleh '.$this->llm->label().'. Tinjau dan sunting sebelum dipakai.'
                : 'Kerangka empat kuadran dibuat tanpa AI. Lengkapi narasinya bersama tim.';
        }

        return [
            'status' => $status->value,
            'message' => $message,
            'notice' => $scenario->generation_notice,
            'error' => $scenario->generation_error,
        ];
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

    /**
     * Scenario with its quadrants and evidence. Citations are returned as the
     * S-references the narrative uses, with the signals behind them.
     */
    protected function present(Scenario $scenario): array
    {
        $scenario->load(['axis_a.dimension', 'axis_b.dimension', 'quadrants']);
        $evidence = $this->scenarios->storedEvidence($scenario);
        $refById = collect($scenario->input_signal_ids ?? [])->values()->mapWithKeys(fn ($id, $index) => [$id => 'S'.($index + 1)]);
        $axis = fn ($force, $low, $high) => [
            'id' => $force?->uuid,
            'keyword' => $force?->keyword ?? '(Faktor dihapus)',
            'dimension' => $force?->dimension?->name,
            'is_deleted' => $force === null || $force->trashed(),
            'low' => $low,
            'high' => $high,
        ];

        return [
            'id' => $scenario->uuid,
            'name' => $scenario->name,
            'focal_question' => $scenario->focal_question,
            'status' => $scenario->status,
            'axis_a' => $axis($scenario->axis_a, $scenario->axis_a_low_label, $scenario->axis_a_high_label),
            'axis_b' => $axis($scenario->axis_b, $scenario->axis_b_low_label, $scenario->axis_b_high_label),
            'provider' => $scenario->provider,
            'model' => $scenario->model,
            'prompt_version' => $scenario->prompt_version,
            'generated_at' => $scenario->generated_at,
            'generation' => $this->generation($scenario),
            'evidence' => $evidence,
            'quadrants' => $scenario->quadrants->map(fn ($quadrant) => [
                'id' => $quadrant->uuid,
                'position' => $quadrant->position,
                'poles' => $scenario->poles($quadrant->position),
                'title' => $quadrant->title,
                'narrative' => $quadrant->narrative,
                'implications' => $quadrant->implications,
                'early_indicators' => $quadrant->early_indicators ?? [],
                'cited_refs' => collect($quadrant->cited_signal_ids ?? [])->map(fn ($id) => $refById[$id] ?? null)->filter()->values()->all(),
                'is_ai_generated' => $quadrant->is_ai_generated,
                'provider' => $quadrant->provider,
                'edited' => $quadrant->isEdited(),
                'edited_at' => $quadrant->edited_at,
            ])->values()->all(),
        ];
    }
}
