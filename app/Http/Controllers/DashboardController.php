<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Concerns\ReportsByScope;
use App\Http\Requests\SimulationRequest;
use App\Models\Dimension;
use App\Services\ReasoningEngineService;
use App\Services\ScenarioSimulationService;
use App\Support\ReportScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    use HandlesApiErrors, ReportsByScope;

    public function index(Request $request)
    {
        $dimensions = Dimension::options();

        return Inertia::render('Dashboard', compact('dimensions') + $this->scopeProps($request));
    }

    public function reasoning_data(Request $request, ReasoningEngineService $reasoningService)
    {
        // Resolved outside the try: an unknown or forbidden Key Focal Issue is a 404 / 403, not a 500.
        $scope = ReportScope::fromRequest($request);
        try {
            $dateRange = $request->input('date');
            $dimension = $request->input('dimension');

            $data = $reasoningService->getExecutiveOverview($dateRange, $dimension, $scope->project);

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
        $scope = ReportScope::fromRequest($request);
        try {
            $validated = $request->validated();

            return response()->json(
                $simulation->simulate(
                    $validated['date'] ?? null,
                    $validated['dimension'] ?? null,
                    $validated['overrides'],
                    (bool) ($validated['propagate'] ?? false),
                    $scope->project
                ),
                Response::HTTP_OK
            );
        } catch (\Throwable $th) {
            return $this->errorJsonResponse($th, 'running scenario simulation');
        }
    }
}
