<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\IngestSourceRequest;
use App\Http\Requests\ReviewSignalRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\Signal;
use App\Models\TimeHorizon;
use App\Services\SignalExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class SignalIngestionController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected SignalExtractionService $extractionService)
    {
    }

    /**
     * Render the Signal Ingestion and Candidate Review workspace.
     */
    public function index(): InertiaResponse
    {
        $dimensions = Dimension::select('id', 'name')->get()->map(fn ($d) => [
            'value' => $d->id,
            'label' => $d->name,
        ]);

        $timeHorizons = TimeHorizon::select('id', 'name', 'code')->get()->map(fn ($th) => [
            'value' => $th->id,
            'label' => $th->name,
        ]);

        $drivingForces = DrivingForce::select('id', 'keyword', 'dimension_id', 'status')
            ->with('dimension:id,name')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get()
            ->map(fn ($df) => [
                'value' => $df->id,
                'label' => $df->keyword . ' (' . ($df->dimension->name ?? 'General') . ') [' . $df->status . ']',
            ]);

        return Inertia::render('Signals/Index', [
            'title' => 'Signal Ingestion & Review',
            'dimensions' => $dimensions,
            'timeHorizons' => $timeHorizons,
            'existingDrivingForces' => $drivingForces,
        ]);
    }

    /**
     * Paginated fetch for candidate signals with filtering.
     */
    public function fetch_signals(Request $request): JsonResponse
    {
        try {
            $status = $request->query('status', 'PENDING');
            $dimension = $request->query('dimension');
            $search = $request->query('search');

            $signals = Signal::with(['source', 'dimension', 'suggested_time_horizon', 'reviewer', 'created_driving_force'])
                ->when($status && $status !== 'ALL', fn ($q) => $q->where('review_status', $status))
                ->when($dimension, fn ($q) => $q->where('dimension_id', $dimension))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('title', 'LIKE', '%' . $search . '%')
                            ->orWhere('summary', 'LIKE', '%' . $search . '%')
                            ->orWhere('evidence_quote', 'LIKE', '%' . $search . '%');
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->query('pageSize', 10));

            return response()->json($signals, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching signals');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ingest source and extract candidate signals.
     */
    public function ingest(IngestSourceRequest $request): JsonResponse
    {
        try {
            $result = $this->extractionService->ingestAndExtract($request->validated(), auth()->id());

            return response()->json([
                'message' => 'Source ingested and ' . count($result['signals']) . ' candidate signal(s) extracted successfully.',
                'source' => $result['source'],
                'signals' => $result['signals'],
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'ingesting source content');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Review, edit, accept, or reject candidate signal.
     */
    public function review(ReviewSignalRequest $request, Signal $signal): JsonResponse
    {
        try {
            $validated = $request->validated();
            $action = $validated['action'];

            return DB::transaction(function () use ($validated, $action, $signal) {
                // Apply any edited fields
                if (!empty($validated['title'])) { $signal->title = $validated['title']; }
                if (!empty($validated['summary'])) { $signal->summary = $validated['summary']; }
                if (!empty($validated['evidence_quote'])) { $signal->evidence_quote = $validated['evidence_quote']; }
                if (!empty($validated['dimension_id'])) { $signal->dimension_id = $validated['dimension_id']; }
                if (!empty($validated['suggested_time_horizon_id'])) { $signal->suggested_time_horizon_id = $validated['suggested_time_horizon_id']; }
                if (isset($validated['preliminary_impact'])) { $signal->preliminary_impact = $validated['preliminary_impact']; }
                if (isset($validated['preliminary_uncertainty'])) { $signal->preliminary_uncertainty = $validated['preliminary_uncertainty']; }

                if ($action === 'reject') {
                    $signal->review_status = 'REJECTED';
                    $signal->rejection_reason = $validated['rejection_reason'] ?? 'Rejected by analyst';
                    $signal->reviewed_by = auth()->id();
                    $signal->reviewed_at = now();
                    $signal->save();

                    return response()->json([
                        'message' => 'Signal successfully rejected with feedback.',
                        'signal' => $signal->fresh(),
                    ], Response::HTTP_OK);
                }

                if ($action === 'accept_link') {
                    $dfId = $validated['driving_force_id'];
                    $signal->review_status = 'ACCEPTED';
                    $signal->reviewed_by = auth()->id();
                    $signal->reviewed_at = now();
                    $signal->save();

                    $signal->driving_forces()->syncWithoutDetaching([
                        $dfId => ['notes' => 'Linked during analyst review from source #' . $signal->source_id],
                    ]);

                    return response()->json([
                        'message' => 'Signal accepted and linked to existing driving force.',
                        'signal' => $signal->fresh(['driving_forces']),
                    ], Response::HTTP_OK);
                }

                if ($action === 'accept_create') {
                    // Normalize keyword to first 4 words to respect WordCountRule
                    $words = explode(' ', trim($signal->title));
                    $keyword = implode(' ', array_slice($words, 0, 4));

                    $drivingForce = DrivingForce::create([
                        'uuid' => (string) Uuid::uuid4(),
                        'dimension_id' => $signal->dimension_id ?? Dimension::first()->id,
                        'created_by' => auth()->id(),
                        'pic' => auth()->id(),
                        'keyword' => $keyword,
                        'description' => $signal->summary,
                        'status' => 'PENDING',
                    ]);

                    $signal->review_status = 'ACCEPTED';
                    $signal->created_driving_force_id = $drivingForce->id;
                    $signal->reviewed_by = auth()->id();
                    $signal->reviewed_at = now();
                    $signal->save();

                    $signal->driving_forces()->attach($drivingForce->id, [
                        'notes' => 'Source signal originated this driving force',
                    ]);

                    return response()->json([
                        'message' => 'Signal accepted and successfully converted to Driving Force in PENDING status.',
                        'signal' => $signal->fresh(['created_driving_force']),
                        'driving_force' => $drivingForce,
                    ], Response::HTTP_OK);
                }

                // If just updating metadata
                $signal->save();
                return response()->json([
                    'message' => 'Signal metadata updated successfully.',
                    'signal' => $signal->fresh(),
                ], Response::HTTP_OK);
            });
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'reviewing signal');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
