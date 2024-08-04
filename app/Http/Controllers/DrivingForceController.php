<?php

namespace App\Http\Controllers;

use App\Http\Requests\DrivingForceRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
{
    //

    public function index()
    {
        $dimensions = Dimension::all()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $status = [
            [
                "label" => "pending",
                "value" => "PENDING",
            ],
            [
                "label" => "approved",
                "value" => "APPROVED",
            ],
            [
                "label" => "rejected",
                "value" => "REJECTED",
            ],
        ];

        return Inertia::render("DrivingForce/Table", compact('dimensions', 'status'));
    }

    public function fetch_data()
    {
        try {
            $result = DrivingForce::filter();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(DrivingForceRequest $request)
    {
        try {
            DB::beginTransaction();
            $request['uuid'] = Uuid::uuid1();
            $request['created_by'] = auth()->user()->id;
            $request['status'] = "PENDING";

            $request = $request->all();

            DrivingForce::firstOrCreate([
                'keyword' => $request['keyword'],
            ], $request);

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_CREATED,
                'message' => 'Successfully create new signal !'
            ];
        } catch (\Throwable $th) {
            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }

        return response()->json($response, $response['statusCode']);
    }

    public function update(DrivingForceRequest $request, DrivingForce $driving_force)
    {
        try {
            DB::beginTransaction();

            if ($request['status'] != $driving_force->status) {
                $request['updated_by'] = auth()->user()->id;
            }
            if ($request['status'] == 'APPROVED' && $driving_force->status != 'APPROVED') {
                $request['approved_at'] = now();
            }

            $request = $request->all();

            $driving_force->dimension_id = $request['dimension_id'];
            $driving_force->updated_by = $request['updated_by'] ?? null;
            $driving_force->keyword = $request['keyword'];
            $driving_force->description = $request['description'];
            $driving_force->status = $request['status'];
            $driving_force->remark = $request['remark'];
            $driving_force->approved_at = $request['approved_at'] ?? null;
            $driving_force->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully update new signal !'
            ];
        } catch (\Throwable $th) {
            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }

        return response()->json($response, $response['statusCode']);
    }

    public function delete(DrivingForce $driving_force)
    {
        try {
            DB::beginTransaction();

            $driving_force->delete();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully delete the signal changes !'
            ];
        } catch (\Throwable $th) {
            //throw $th;

            DB::rollBack();

            $response = [
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $th->getMessage(),
            ];
        }

        return response()->json($response, $response['statusCode']);
    }
}
