<?php

namespace App\Http\Controllers;

use App\Models\Dimension;
use App\Models\DrivingForceRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ForesightRadarController extends Controller
{
    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $title = "Foresight Radar";

        return Inertia::render('Report/ForesightRadar', compact('dimensions', 'title'));
    }

    public function foresight_radar()
    {
        try {
            $result = DrivingForceRating::foresight_radar();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Foresight radar fetch error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'An unexpected server error occurred while retrieving data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
