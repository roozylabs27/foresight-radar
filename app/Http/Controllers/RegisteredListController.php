<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Controllers\Concerns\ReportsByScope;
use App\Http\Resources\OverallStatusCollection;
use App\Http\Resources\OverallStatusResource;
use App\Models\Dimension;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
use App\Services\ForesightReportingService;
use App\Support\ReportScope;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RegisteredListController extends Controller
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
        $time_horizons = TimeHorizon::all()->map(function ($time_horizon) {
            return [
                'value' => $time_horizon->id,
                'label' => $time_horizon->name,
            ];
        });
        $priorities = Priority::all()->map(function ($priority) {
            return [
                'value' => $priority->id,
                'label' => $priority->name,
            ];
        });
        $status_actions = StatusAction::all()->map(function ($status_action) {
            return [
                'value' => $status_action->id,
                'label' => $status_action->name,
            ];
        });
        $title = 'Registered List';

        return Inertia::render('Report/RegisteredList', compact('dimensions', 'title', 'priorities', 'status_actions', 'time_horizons') + $this->scopeProps($request));
    }

    public function registered_list(Request $request, ForesightReportingService $reportingService)
    {
        // Resolved outside the try: an unknown or forbidden Key Focal Issue is a 404 / 403, not a 500.
        $scope = ReportScope::fromRequest($request);
        try {
            $rawPagination = (int) request('pagination.pageSize', request('pageSize', 10));
            $pagination = min(100, max(1, $rawPagination > 0 ? $rawPagination : 10));
            if ($scope->isFirm()) {
                $registered_list = $reportingService->getRegisteredListQuery()->paginate($pagination);
            } else {
                // An exercise holds tens of forces, so it is paged in memory.
                $rows = $reportingService->getExerciseRegisteredList($scope->project);
                $page = max(1, (int) request('page', 1));
                $registered_list = new LengthAwarePaginator($rows->forPage($page, $pagination)->values(), $rows->count(), $pagination, $page, ['path' => $request->url()]);
            }
            $result = new OverallStatusCollection($registered_list);

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return $this->errorJsonResponse(
                $th,
                'fetching registered list data',
                'An unexpected server error occurred while retrieving data.'
            );
        }
    }

    public function export_data(Request $request, ForesightReportingService $reportingService)
    {
        $scope = ReportScope::fromRequest($request);
        try {
            $rows = $scope->isFirm()
                ? $reportingService->getRegisteredListQuery()->get()
                : $reportingService->getExerciseRegisteredList($scope->project);

            return response()->stream(function () use ($rows) {
                echo '[';
                $first = true;
                foreach ($rows as $item) {
                    if (! $first) {
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
            return $this->errorJsonResponse(
                $th,
                'exporting registered list data',
                'An unexpected server error occurred while retrieving data.'
            );
        }
    }
}
