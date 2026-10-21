<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Services\ForesightReportingService;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PrioritizingController extends Controller
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
        $title = 'Prioritizing';

        return Inertia::render('Report/Prioritizing', compact('dimensions', 'title'));
    }

    public function prioritizing(ForesightReportingService $reportingService)
    {
        try {
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
