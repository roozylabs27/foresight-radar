<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioQuadrantRequest;
use App\Http\Requests\ScenarioRequest;
use App\Models\Scenario;
use App\Models\ScenarioQuadrant;
use App\Services\Llm\StructuredLlmClient;
use App\Services\ScenarioNarrativeService;
use App\Services\ScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ScenarioController extends Controller
{
    public function __construct(
        protected ScenarioService $scenarios,
        protected ScenarioNarrativeService $narratives,
        protected StructuredLlmClient $llm,
    ) {
    }

    public function index(): InertiaResponse
    {
        return Inertia::render('Report/Scenarios', [
            'title' => 'Scenarios',
            'canManage' => auth()->user()->can('manage-scenario'),
            'llm' => ['available' => $this->llm->isAvailable(), 'model' => $this->llm->isAvailable() ? $this->llm->model() : null],
        ]);
    }

    public function fetch(): JsonResponse
    {
        $scenarios = Scenario::with(['axis_a:id,uuid,keyword', 'axis_b:id,uuid,keyword'])
            ->withCount('quadrants')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($scenario) => [
                'id' => $scenario->uuid,
                'name' => $scenario->name,
                'status' => $scenario->status,
                'provider' => $scenario->provider,
                'axis_a' => $scenario->axis_a?->keyword,
                'axis_b' => $scenario->axis_b?->keyword,
                'quadrants_count' => $scenario->quadrants_count,
                'updated_at' => $scenario->updated_at,
            ]);

        return response()->json($scenarios, Response::HTTP_OK);
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
        $scenario->delete();

        return response()->json(['message' => 'Skenario dihapus.'], Response::HTTP_OK);
    }

    public function updateQuadrant(ScenarioQuadrantRequest $request, Scenario $scenario, ScenarioQuadrant $quadrant): JsonResponse
    {
        abort_unless($quadrant->scenario_id === $scenario->id, Response::HTTP_NOT_FOUND);

        $quadrant->update($request->validated() + [
            'early_indicators' => $request->validated('early_indicators', []),
            'edited_by' => auth()->id(),
            'edited_at' => now(),
        ]);

        return response()->json(['message' => 'Kuadran disimpan.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    public function generate(Request $request, Scenario $scenario): JsonResponse
    {
        $result = $this->narratives->generate($scenario, $request->boolean('overwrite_edited'), auth()->id());

        $message = $result['provider'] === 'claude'
            ? 'Narasi empat kuadran dibuat oleh Claude. Tinjau dan sunting sebelum dipakai.'
            : 'Kerangka empat kuadran dibuat tanpa AI. Lengkapi narasinya bersama tim.';

        return response()->json([
            'message' => $message,
            'provider' => $result['provider'],
            'notice' => $result['notice'],
            'scenario' => $this->present($result['scenario']),
        ], Response::HTTP_OK);
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
        $refById = collect($scenario->input_signal_ids ?? [])->values()->mapWithKeys(fn ($id, $index) => [$id => 'S' . ($index + 1)]);
        $axis = fn ($force, $low, $high) => [
            'id' => $force?->uuid,
            'keyword' => $force?->keyword,
            'dimension' => $force?->dimension?->name,
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
