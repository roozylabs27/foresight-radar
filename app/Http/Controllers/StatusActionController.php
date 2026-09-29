<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\StatusActionRequest;
use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DrivingForceService;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class StatusActionController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService)
    {
    }

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
            $result = $this->drivingForceService->statusAction(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching status actions');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
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

            $action_reason = new ActionReason();
            $action_reason->uuid = Uuid::uuid1();
            $action_reason->driving_force_rating_id = $driving_force_rating->id;
            $action_reason->status_action_id = $request['status_action_id'];
            $action_reason->reason = $request['reason'];
            $action_reason->date = now();
            $action_reason->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set status action for ' . $driving_force_rating->driving_force->keyword
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'setting status action');
        }

        return response()->json($response, $response['statusCode']);
    }
}
