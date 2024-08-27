<?php

namespace App\Http\Controllers;

use App\Http\Requests\DrivingForceRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
{
    //
    public function index()
    {
        $dimensions = Dimension::select('id', 'name')->get()->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name
            ];
        });
        $users = User::select('id', 'name')->whereHas('roles', function ($q) {
            $q->whereNotIn('name', ['super-admin', 'developer']);
        })->orderBy('name', 'asc')->get()->map(function ($user) {
            return [
                'value' => $user->id,
                'label' => $user->name . ' - ' . $user->roles->pluck('display_name')[0]
            ];
        });

        $title = "Driving Force";

        return Inertia::render("DrivingForce/Table", compact('dimensions', 'title', 'users'));
    }

    public function fetch_data()
    {
        try {
            $result = DrivingForce::filter();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
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
            $request['pic'] = $request['pic_id'];
            $request['status'] = "PENDING";

            $request = $request->all();

            DrivingForce::create($request);

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

            $request = $request->all();

            $driving_force->dimension_id = $request['dimension_id'];
            $driving_force->updated_by = $request['updated_by'] ?? null;
            $driving_force->pic = $request['pic_id'];
            $driving_force->keyword = $request['keyword'];
            $driving_force->description = $request['description'];
            $driving_force->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully update the signal !'
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
                'message' => 'Successfully delete the signal !'
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
