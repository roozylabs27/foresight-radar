<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Services\KnowledgeGraphService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class KnowledgeGraphController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        protected KnowledgeGraphService $knowledgeGraphService
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Visualization/KnowledgeGraph', [
            'title' => 'Signal Knowledge Graph',
        ]);
    }

    public function data(): JsonResponse
    {
        try {
            return response()->json($this->knowledgeGraphService->graphData(), Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse(
                $th,
                'fetching knowledge graph data',
                'An unexpected error occurred while retrieving knowledge graph data.'
            );
        }
    }
}
