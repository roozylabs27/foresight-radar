<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Concerns\ReportsByScope;
use App\Models\Dimension;
use App\Services\ForesightReportingService;
use App\Support\ReportScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ForesightRadarController extends Controller
{
    use HandlesApiErrors, ReportsByScope;

    public function index(Request $request)
    {
        $dimensions = Dimension::options();
        $title = 'Foresight Radar';

        return Inertia::render('Report/ForesightRadar', compact('dimensions', 'title') + $this->scopeProps($request));
    }

    public function foresight_radar(Request $request, ForesightReportingService $reportingService)
    {
        // Resolved outside the try: an unknown or forbidden Key Focal Issue is a 404 / 403, not a 500.
        $scope = ReportScope::fromRequest($request);
        try {
            if (! $scope->isFirm()) {
                [$result, $skipped] = $reportingService->getExerciseRadarDataset($scope->project);

                return $this->withSkipped(response()->json($result, Response::HTTP_OK), $skipped);
            }
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
