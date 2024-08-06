<?php

namespace App\Http\Controllers;

use App\Http\Requests\StatusActionRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class StatusActionController extends Controller
{
    public function index()
    {
        $title = 'Status Action';
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $status_actions = StatusAction::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        return Inertia::render("StatusAction/Table", compact('dimensions', 'title', 'status_actions'));
    }

    public function fetch_data()
    {
        try {
            $result = DrivingForce::status_action();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(StatusActionRequest $request, DrivingForceRating $driving_force_rating)
    {
        try {
            DB::beginTransaction();

            $driving_force_rating->status_action_id = $request['status_action_id'];

            if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis >= 6) {
                $driving_force_rating->priority_id = 1;
            } else if ($driving_force_rating->impact_analysis >= 6 && $driving_force_rating->uncertainty_analysis <= 5) {
                $driving_force_rating->priority_id = 2;
            } else if ($driving_force_rating->impact_analysis <= 5 && $driving_force_rating->uncertainty_analysis >= 6) {
                $driving_force_rating->priority_id = 2;
            } else {
                $driving_force_rating->priority_id = 3;
            }

            $driving_force_rating->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set status action for ' . $driving_force_rating->driving_force->keyword
            ];
        } catch (\Throwable $th) {

            DB::rollBack();

            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }

        return response()->json($response, $response['statusCode']);
    }
}
