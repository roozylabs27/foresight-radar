<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\SimulationRequest;
use App\Models\Dimension;
use App\Services\ReasoningEngineService;
use App\Services\ScenarioSimulationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name,
            ];
        });

        return Inertia::render('Dashboard', compact('dimensions'));
    }

    public function reasoning_data(Request $request, ReasoningEngineService $reasoningService)
    {
        try {
            $dateRange = $request->input('date');
            $dimension = $request->input('dimension');

            $data = $reasoningService->getExecutiveOverview($dateRange, $dimension);

            return response()->json($data, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'retrieving executive overview reasoning data');
        }
    }

    /**
     * What-if: the indices and posture with some forces changed. Nothing is saved.
     */
    public function simulate(SimulationRequest $request, ScenarioSimulationService $simulation)
    {
        try {
            $validated = $request->validated();

            return response()->json(
                $simulation->simulate($validated['date'] ?? null, $validated['dimension'] ?? null, $validated['overrides']),
                Response::HTTP_OK
            );
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'running scenario simulation');
        }
    }
}
