<?php

namespace App\Http\Controllers;

use App\Enums\LlmJobStatus;
use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\IngestSourceRequest;
use App\Http\Requests\ReviewSignalRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\Signal;
use App\Models\Source;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\SignalExtractionService;
use App\Support\EvidenceVerifier;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class SignalIngestionController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected SignalExtractionService $extractionService) {}

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
                'label' => $df->keyword.' ('.($df->dimension->name ?? 'General').') ['.$df->status.']',
            ]);

        /** @var User|null $user */
        $user = auth()->user();

        return Inertia::render('Signals/Index', [
            'title' => 'Signal Ingestion & Review',
            'dimensions' => $dimensions,
            'timeHorizons' => $timeHorizons,
            'existingDrivingForces' => $drivingForces,
            'extractionMode' => $this->extractionService->mode($user),
            'extractionModel' => $this->extractionService->modelName($user),
            'extractionLabel' => $this->extractionService->modeLabel($user),
            'activeLlm' => [
                'mode' => $this->extractionService->mode($user),
                'model' => $this->extractionService->modelName($user),
                'label' => $this->extractionService->modeLabel($user),
                'isCustom' => (bool) $user?->llmSettings()->where('is_active', true)->exists(),
            ],
            'maxContentChars' => (int) config('services.signal_extraction.max_chars', 60000),
            // The analyst's extractions still in the queue, so the page resumes polling them.
            'pendingExtractions' => Source::where('created_by', auth()->id())
                ->whereIn('extraction_status', [LlmJobStatus::QUEUED, LlmJobStatus::PROCESSING])
                ->where('created_at', '>=', now()->subDay())
                ->latest()
                ->get()
                ->map(fn ($source) => $this->extractionService->progress($source))
                ->values(),
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

            $rawPageSize = (int) $request->query('pageSize', $request->input('pagination.pageSize', 10));
            $pageSize = min(100, max(1, $rawPageSize > 0 ? $rawPageSize : 10));

            $signals = Signal::with(['source:id,uuid,title,url,publisher,published_at', 'dimension', 'suggested_time_horizon', 'reviewer', 'created_driving_force', 'suggested_driving_force:id,uuid,keyword'])
                ->when($status && $status !== 'ALL', fn ($q) => $q->where('review_status', $status))
                ->when($dimension, fn ($q) => $q->where('dimension_id', $dimension))
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('title', 'LIKE', '%'.$search.'%')
                            ->orWhere('summary', 'LIKE', '%'.$search.'%')
                            ->orWhere('evidence_quote', 'LIKE', '%'.$search.'%');
                    });
                })
                ->tap(fn ($q) => TableSort::apply(
                    $q,
                    $request->query(),
                    [
                        'title' => 'title',
                        'taxonomy' => Dimension::select('name')->whereColumn('dimensions.id', 'signals.dimension_id'),
                        'assessment' => 'confidence_score',
                        'review_status' => 'review_status',
                    ],
                    fn ($q) => $q->orderBy('created_at', 'desc')
                ))
                ->paginate($pageSize);

            return response()->json($signals, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching signals');

            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Save the source and queue signal extraction. The client polls
     * `extraction` until the status is completed or failed.
     */
    public function ingest(IngestSourceRequest $request): JsonResponse
    {
        try {
            $source = $this->extractionService->ingest($request->validated(), auth()->id());

            return response()->json([
                'message' => 'Sumber disimpan. Sinyal sedang diekstrak di latar belakang.',
                'source' => $this->extractionService->progress($source),
            ], Response::HTTP_ACCEPTED);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'ingesting source content');

            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Extraction status of an ingested source, with the result once it is done.
     */
    public function extraction(Source $source): JsonResponse
    {
        $user = auth()->user();
        $isOwnerOrAdmin = $user && (
            $source->created_by === $user->id
            || $user->hasAnyRole(['developer', 'super-admin', 'admin'])
        );

        if (! $isOwnerOrAdmin) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki akses ke status ekstraksi sumber ini.');
        }

        return response()->json($this->extractionService->progress($source), Response::HTTP_OK);
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
                $signal = Signal::whereKey($signal->id)->lockForUpdate()->firstOrFail();

                if ($signal->review_status !== 'PENDING') {
                    return response()->json([
                        'errors' => 'Sinyal ini sudah direview ('.$signal->review_status.') dan tidak bisa diubah lagi.',
                    ], Response::HTTP_CONFLICT);
                }
                // Apply any edited fields
                if (! empty($validated['title'])) {
                    $signal->title = $validated['title'];
                }
                if (! empty($validated['summary'])) {
                    $signal->summary = $validated['summary'];
                }
                if (! empty($validated['evidence_quote']) && $validated['evidence_quote'] !== $signal->evidence_quote) {
                    // An edited quote must still be found verbatim in the source.
                    $signal->evidence_quote = $validated['evidence_quote'];
                    $signal->quote_verified = EvidenceVerifier::verify($validated['evidence_quote'], $signal->source?->raw_content);
                }
                if (! empty($validated['dimension_id'])) {
                    $signal->dimension_id = $validated['dimension_id'];
                }
                if (! empty($validated['suggested_time_horizon_id'])) {
                    $signal->suggested_time_horizon_id = $validated['suggested_time_horizon_id'];
                }
                if (isset($validated['preliminary_impact'])) {
                    $signal->preliminary_impact = $validated['preliminary_impact'];
                }
                if (isset($validated['preliminary_uncertainty'])) {
                    $signal->preliminary_uncertainty = $validated['preliminary_uncertainty'];
                }

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

                if (in_array($action, ['accept_link', 'accept_create'], true)
                    && (trim((string) $signal->evidence_quote) === '' || $signal->quote_verified === false)) {
                    throw ValidationException::withMessages([
                        'evidence_quote' => 'Kutipan bukti tidak ditemukan verbatim di dokumen sumber, jadi sinyal ini tidak bisa diterima. Perbaiki kutipannya atau tolak sinyal.',
                    ]);
                }

                if ($action === 'accept_link') {
                    $dfId = $validated['driving_force_id'];
                    $signal->review_status = 'ACCEPTED';
                    $signal->reviewed_by = auth()->id();
                    $signal->reviewed_at = now();
                    $signal->save();

                    $signal->driving_forces()->syncWithoutDetaching([
                        $dfId => ['notes' => 'Linked during analyst review from source #'.$signal->source_id],
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

                    // Carry the signal's horizon and preliminary scores into the
                    // rating; the status action is left for the analyst. A rating
                    // needs a horizon, so without one the Time Horizon stage starts it.
                    if ($signal->suggested_time_horizon_id) {
                        $rating = new DrivingForceRating([
                            'driving_force_id' => $drivingForce->id,
                            'time_horizon_id' => $signal->suggested_time_horizon_id,
                            'impact_analysis' => $signal->preliminary_impact,
                            'uncertainty_analysis' => $signal->preliminary_uncertainty,
                        ]);
                        $rating->priority_id = $rating->calculatePriority();
                        $rating->save();
                    }

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'reviewing signal');

            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
