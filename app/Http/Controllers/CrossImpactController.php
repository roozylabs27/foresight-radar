<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\CrossImpactRequest;
use App\Services\CrossImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CrossImpactController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected CrossImpactService $crossImpactService) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Report/CrossImpact', [
            'title' => 'Cross-Impact',
            'canManage' => Gate::allows('manage-cross-impact'),
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            return response()->json($this->crossImpactService->matrix(), Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'fetching cross-impact matrix');
        }
    }

    public function update(CrossImpactRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $impact = $this->crossImpactService->score(
                $validated['source'],
                $validated['target'],
                (int) $validated['score'],
                $validated['note'] ?? null,
                auth()->id(),
            );

            return response()->json([
                'message' => $impact ? 'Cross-impact disimpan.' : 'Cross-impact dihapus.',
                'matrix' => $this->crossImpactService->matrix(),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'updating cross-impact score');
        }
    }
}
