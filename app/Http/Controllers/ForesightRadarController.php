<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Services\ForesightReportingService;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ForesightRadarController extends Controller
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
        $title = 'Foresight Radar';

        return Inertia::render('Report/ForesightRadar', compact('dimensions', 'title'));
    }

    public function foresight_radar(ForesightReportingService $reportingService)
    {
        try {
            $result = $reportingService->getRadarDataset();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse(
                $th,
                'fetching foresight radar data',
                'An unexpected server error occurred while retrieving data.'
            );
        }
    }
}
