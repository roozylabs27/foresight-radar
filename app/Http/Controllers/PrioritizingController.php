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

class PrioritizingController extends Controller
{
    use HandlesApiErrors, ReportsByScope;

    public function index(Request $request)
    {
        $dimensions = Dimension::ordered()->get()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name,
            ];
        });
        $title = 'Prioritizing';

        return Inertia::render('Report/Prioritizing', compact('dimensions', 'title') + $this->scopeProps($request));
    }

    public function prioritizing(Request $request, ForesightReportingService $reportingService)
    {
        // Resolved outside the try: an unknown or forbidden Key Focal Issue is a 404 / 403, not a 500.
        $scope = ReportScope::fromRequest($request);
        try {
            if (! $scope->isFirm()) {
                [$result, $skipped] = $reportingService->getExercisePrioritizingDataset($scope->project);

                return $this->withSkipped(response()->json($result, Response::HTTP_OK), $skipped);
            }
            $result = $reportingService->getPrioritizingDataset();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse(
                $th,
                'fetching prioritizing data',
                'An unexpected server error occurred while retrieving data.'
            );
        }
    }
}
