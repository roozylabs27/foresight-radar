<?php

namespace App\Http\Controllers;

use App\Http\Resources\OverallStatusCollection;
use App\Http\Resources\OverallStatusResource;
use App\Models\Dimension;
use App\Models\DrivingForceRating;
use App\Models\Priority;
use App\Models\StatusAction;
use App\Models\TimeHorizon;
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

    public function registered_list()
    {
        try {

            $pagination = request('pagination.pageSize');
            $registered_list = DrivingForceRating::registered_list()->paginate($pagination);
            $result = new OverallStatusCollection($registered_list);

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function export_data()
    {
        try {
            $registered_list = DrivingForceRating::registered_list()->get();
            $result = OverallStatusResource::collection($registered_list);

            return response()->json($result, Response::HTTP_OK);

        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
