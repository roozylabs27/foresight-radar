<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ApprovalController extends Controller
{
    use HandlesApiErrors;
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
            $result = DrivingForce::approval_items();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching approval items');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(Request $request, DrivingForce $driving_force)
    {
        try {
            DB::beginTransaction();
            if($request->status == 'CLOSED') {
                $driving_force->closed_at = now();
            }
            if($request->status == 'APPROVED') {
                $driving_force->approved_at = now();
            }
            $driving_force->status = $request->status;
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
