<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DrivingForceService;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ApprovalController extends Controller
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

        $title = "Approval Items";

        return Inertia::render("Approval/Table", compact('dimensions', 'title'));
    }

    public function fetch_data()
    {
        try {
            $result = $this->drivingForceService->approvalItems(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching approval items');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request, DrivingForce $driving_force)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:APPROVED,CLOSED,REJECTED,PENDING'],
            'text' => ['nullable', 'string'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'APPROVED') {
            if (!$driving_force->relationLoaded('rating')) {
                $driving_force->load('rating');
            }
            if (!$driving_force->rating || is_null($driving_force->rating->status_action_id)) {
                return response()->json([
                    'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    'errors' => 'Cannot approve a driving force without an assigned Status of Action.',
                    'message' => 'Cannot approve a driving force without an assigned Status of Action.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $driving_force->transitionTo($validated['status']);
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return response()->json([
                'statusCode' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'errors' => $e->getMessage(),
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();
            if ($validated['status'] == 'CLOSED') {
                $driving_force->closed_at = now();
            }
            if ($validated['status'] == 'APPROVED') {
                $driving_force->approved_at = now();
            }
            if ($validated['status'] == 'REJECTED') {
                $driving_force->remark = $validated['remark'] ?? null;
                $driving_force->approved_at = null;
            }
            $driving_force->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully ' . $request->text . ' for ' . $driving_force->keyword . ' !'
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'processing approval item');
        }

        return response()->json($response, $response['statusCode']);
    }
}
