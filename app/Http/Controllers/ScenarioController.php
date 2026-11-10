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
use App\Services\StalenessService;
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
        if ($scenario->isFrameworkApproved() && $request->validated('title') !== $quadrant->title) {
            throw ValidationException::withMessages(['title' => 'Nama kuadran terkunci oleh persetujuan kerangka (G5). Buka kembali kerangkanya di latihan untuk mengganti nama.']);
        }

        // An edited narrative no longer matches its typed statements (roadmap R2.7); citations stay, untyped.
        if ($request->validated('narrative') !== $quadrant->narrative && $quadrant->narrative_statements !== null) {
            $quadrant->narrative_statements = null;
            $quadrant->citations()->update(['statement_kind' => null, 'statement_index' => null]);
        }
        $quadrant->update($request->validated() + [
            'early_indicators' => $request->validated('early_indicators', []),
            'edited_by' => Auth::id(),
            'edited_at' => now(),
        ] + ($quadrant->review_status === 'approved' ? ['review_status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null] : []));
        app(StalenessService::class)->clearQuadrant($quadrant);

        return response()->json(['message' => 'Kuadran disimpan.', 'scenario' => $this->present($scenario)], Response::HTTP_OK);
    }

    /**
     * Review state of one quadrant narrative (roadmap R1.6). `approved` is gate G6; an unedited
     * template skeleton is no narrative yet and cannot be approved.
     */
    public function reviewQuadrant(Request $request, Scenario $scenario, ScenarioQuadrant $quadrant): JsonResponse
    {
        $this->authorize('update', $scenario);
        abort_unless($quadrant->scenario_id === $scenario->id, Response::HTTP_NOT_FOUND);

        $status = $request->validate(['status' => ['required', Rule::in(ScenarioQuadrant::REVIEW_STATUSES)]])['status'];
        if ($status !== 'draft' && blank($quadrant->narrative)) {
            throw ValidationException::withMessages(['status' => 'Kuadran ini belum punya narasi.']);
        }
        if ($status === 'approved' && $quadrant->provider === ScenarioNarrativeService::PROVIDER_TEMPLATE && ! $quadrant->isEdited()) {
            throw ValidationException::withMessages(['status' => 'Narasi ini masih kerangka tanpa AI. Lengkapi narasinya dulu sebelum disetujui.']);
        }

        $quadrant->update($status === 'draft'
            ? ['review_status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null]
            : ['review_status' => $status, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

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

    /**
     * Scenario with its quadrants and evidence. Citations are returned as the
     * S-references the narrative uses, with the signals behind them.
     */
    protected function present(Scenario $scenario): array
    {
        $scenario->load(['axis_a.dimension', 'axis_b.dimension', 'axis_a_uncertainty.projectForces.drivingForce', 'axis_b_uncertainty.projectForces.drivingForce', 'quadrants.reviewer:id,name', 'project:id,uuid,name']);
        $evidence = $this->scenarios->storedEvidence($scenario);
        $refById = collect($scenario->input_signal_ids ?? [])->values()->mapWithKeys(fn ($id, $index) => [$id => 'S'.($index + 1)]);
        // A framework axis is a critical uncertainty: its name, and its member forces in place of a dimension.
        $axis = fn (string $letter, $low, $high) => [
            'id' => $scenario->axisUncertainty($letter)?->uuid ?? $scenario->axisForce($letter)?->uuid,
            'keyword' => $scenario->axisName($letter) ?? '(Faktor dihapus)',
            'dimension' => $scenario->axisUncertainty($letter)
                ? $scenario->axisUncertainty($letter)->projectForces->map(fn ($link) => $link->drivingForce?->keyword)->filter()->implode(', ')
                : $scenario->axisForce($letter)?->dimension?->name,
            'is_deleted' => ! $scenario->axisUncertainty($letter) && ($scenario->axisForce($letter) === null || $scenario->axisForce($letter)->trashed()),
            'low' => $low,
            'high' => $high,
        ];

        return [
            'id' => $scenario->uuid,
            'name' => $scenario->name,
            'focal_question' => $scenario->focal_question,
            'status' => $scenario->status,
            'project' => $scenario->project ? ['id' => $scenario->project->uuid, 'name' => $scenario->project->name] : null,
            'framework_status' => $scenario->framework_status,
            'axis_a' => $axis('A', $scenario->axis_a_low_label, $scenario->axis_a_high_label),
            'axis_b' => $axis('B', $scenario->axis_b_low_label, $scenario->axis_b_high_label),
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
                'summary' => $quadrant->summary,
                'narrative' => $quadrant->narrative,
                'statements' => $quadrant->narrative_statements === null ? null : array_map(fn ($row) => [
                    'text' => $row['text'],
                    'kind' => $row['kind'],
                    'refs' => collect($row['signal_ids'] ?? [])->map(fn ($id) => $refById[$id] ?? null)->filter()->values()->all(),
                ], $quadrant->narrative_statements),
                'implications' => $quadrant->implications,
                'early_indicators' => $quadrant->early_indicators ?? [],
                'cited_refs' => collect($quadrant->cited_signal_ids ?? [])->map(fn ($id) => $refById[$id] ?? null)->filter()->values()->all(),
                'is_ai_generated' => $quadrant->is_ai_generated,
                'origin' => $quadrant->origin,
                'provider' => $quadrant->provider,
                'review_status' => $quadrant->review_status,
                'reviewed_by' => $quadrant->reviewer?->name,
                'reviewed_at' => $quadrant->reviewed_at,
                'stale_reason' => $quadrant->stale_since ? $quadrant->stale_reason : null,
                'edited' => $quadrant->isEdited(),
                'edited_at' => $quadrant->edited_at,
            ])->values()->all(),
        ];
    }
}
