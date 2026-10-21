<?php

namespace App\Http\Controllers;

use App\Http\Resources\OverallStatusCollection;
use App\Http\Resources\OverallStatusResource;
use App\Models\Dimension;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Services\ForesightReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RegisteredListController extends Controller
{
    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $time_horizons = TimeHorizon::all()->map(function ($time_horizon) {
            return [
                'value' => $time_horizon->id,
                'label' => $time_horizon->name
            ];
        });
        $priorities = Priority::all()->map(function ($priority) {
            return [
                'value' => $priority->id,
                'label' => $priority->name
            ];
        });
        $status_actions = StatusAction::all()->map(function ($status_action) {
            return [
                'value' => $status_action->id,
                'label' => $status_action->name
            ];
        });
        $title = "Registered List";

        return Inertia::render('Report/RegisteredList', compact('dimensions', 'title', 'priorities', 'status_actions', 'time_horizons'));
    }

    public function registered_list(ForesightReportingService $reportingService)
    {
        try {
            $pagination = request('pagination.pageSize');
            $registered_list = $reportingService->getRegisteredListQuery()->paginate($pagination);
            $result = new OverallStatusCollection($registered_list);

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Registered list fetch error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'An unexpected server error occurred while retrieving data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export_data(ForesightReportingService $reportingService)
    {
        try {
            $query = $reportingService->getRegisteredListQuery();

            return response()->stream(function () use ($query) {
                echo '[';
                $first = true;
                foreach ($query->lazy(100) as $item) {
                    if (!$first) {
                        echo ',';
                    }
                    $first = false;
                    $resource = new OverallStatusResource($item);
                    echo json_encode($resource->resolve());
                }
                echo ']';
            }, Response::HTTP_OK, [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        } catch (\Throwable $th) {
            Log::error('Export data error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'An unexpected server error occurred while retrieving data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
