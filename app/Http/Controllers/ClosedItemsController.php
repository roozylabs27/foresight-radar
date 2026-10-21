<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\ActionReason;
use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DrivingForceService;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class ClosedItemsController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService)
    {
    }

    public function index()
    {
        $dimensions = Dimension::select('id', 'name')->get()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });

        $title = "Closed Items";

        return Inertia::render("ClosedItems/Table", compact('dimensions', 'title'));
    }

    public function fetch_data()
    {
        try {
            $result = $this->drivingForceService->closedItems(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching closed items');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reopen(Request $request, DrivingForce $driving_force)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($driving_force->status !== 'CLOSED') {
            return response()->json([
                'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'errors' => 'Only closed driving forces can be reopened.',
                'message' => 'Only closed driving forces can be reopened.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $driving_force->transitionTo(\App\Enums\DrivingForceStatus::PENDING);
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return response()->json([
                'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $e->getMessage(),
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $driving_force->closed_at = null;
            $driving_force->approved_at = null;
            $driving_force->remark = 'Reopened: ' . $validated['reason'];
            $driving_force->save();

            if (!$driving_force->relationLoaded('rating')) {
                $driving_force->load('rating');
            }

            if ($driving_force->rating && $driving_force->rating->status_action_id) {
                $action_reason = new ActionReason();
                $action_reason->uuid = (string) Uuid::uuid1();
                $action_reason->driving_force_rating_id = $driving_force->rating->id;
                $action_reason->status_action_id = $driving_force->rating->status_action_id;
                $action_reason->reason = 'Reopened: ' . $validated['reason'];
                $action_reason->date = now();
                $action_reason->save();
            }

            DB::commit();

            return response()->json([
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully reopened ' . $driving_force->keyword . ' !',
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'reopening closed item');
            return response()->json($response, $response['statusCode']);
        }
    }
}
