<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

use App\Services\ReasoningEngineService;

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
}
