<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\TimeHorizonRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\TimeHorizon;
use App\Services\DrivingForceService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TimeHorizonController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService) {}

    public function index()
    {
        $title = 'Time Horizon';
        $dimensions = Dimension::all()->map(function ($dimension) {
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

        return Inertia::render('TimeHorizon/Table', compact('dimensions', 'title', 'time_horizons'));
    }

    public function fetch_data()
    {
        try {
            $result = $this->drivingForceService->timeHorizon(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching time horizons');

            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(TimeHorizonRequest $request, DrivingForce $driving_force)
    {
        if ($driving_force->status === 'CLOSED') {
            return response()->json([
                'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Cannot modify time horizon of a closed driving force.',
                'errors' => 'Cannot modify time horizon of a closed driving force.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            DrivingForceRating::updateOrCreate([
                'driving_force_id' => $driving_force->id,
            ], [
                'time_horizon_id' => $request['time_horizon_id'],
            ]);

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set time horizon !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'setting time horizon');
        }

        return response()->json($response, $response['statusCode']);
    }
}
