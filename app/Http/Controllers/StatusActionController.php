<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\StatusActionRequest;
use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForceRating;
use App\Models\StatusAction;
use App\Services\DrivingForceService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class StatusActionController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService) {}

    public function index()
    {
        $title = 'Status Action';
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name,
            ];
        });
        $status_actions = StatusAction::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name,
            ];
        });

        return Inertia::render('StatusAction/Table', compact('dimensions', 'title', 'status_actions'));
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
        if ($driving_force_rating->driving_force?->status === 'CLOSED') {
            return response()->json([
                'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Cannot modify status action of a closed driving force.',
                'errors' => 'Cannot modify status action of a closed driving force.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $driving_force_rating->status_action_id = $request['status_action_id'];

            $driving_force_rating->priority_id = $driving_force_rating->calculatePriority() ?? $driving_force_rating->priority_id;
            $driving_force_rating->save();

            $action_reason = new ActionReason;
            $action_reason->uuid = Uuid::uuid1();
            $action_reason->driving_force_rating_id = $driving_force_rating->id;
            $action_reason->status_action_id = $request['status_action_id'];
            $action_reason->reason = $request['reason'];
            $action_reason->date = now();
            $action_reason->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully set status action for '.$driving_force_rating->driving_force->keyword,
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'setting status action');
        }

        return response()->json($response, $response['statusCode']);
    }
}
