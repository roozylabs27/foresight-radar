<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrossImpactRequest;
use App\Services\CrossImpactService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CrossImpactController extends Controller
{
    public function __construct(protected CrossImpactService $crossImpactService)
    {
    }

    public function index(): InertiaResponse
    {
        return Inertia::render('Report/CrossImpact', [
            'title' => 'Cross-Impact',
            'canManage' => auth()->user()->can('manage-cross-impact'),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->crossImpactService->matrix(), Response::HTTP_OK);
    }

    public function update(CrossImpactRequest $request): JsonResponse
    {
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
    }
}
