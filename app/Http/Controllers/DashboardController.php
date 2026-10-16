<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

use App\Http\Requests\SimulationRequest;
use App\Services\ReasoningEngineService;
use App\Services\ScenarioSimulationService;

class DashboardController extends Controller
{
    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        return Inertia::render('Dashboard', compact('dimensions'));
    }

    public function reasoning_data(Request $request, ReasoningEngineService $reasoningService)
    {
        $dateRange = $request->input('date');
        $dimension = $request->input('dimension');

        $data = $reasoningService->getExecutiveOverview($dateRange, $dimension);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * What-if: the indices and posture with some forces changed. Nothing is saved.
     */
    public function simulate(SimulationRequest $request, ScenarioSimulationService $simulation)
    {
        $validated = $request->validated();

        return response()->json(
            $simulation->simulate($validated['date'] ?? null, $validated['dimension'] ?? null, $validated['overrides']),
            Response::HTTP_OK
        );
    }
}
