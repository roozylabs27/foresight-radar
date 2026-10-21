<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\DrivingForceRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use App\Services\DrivingForceService;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
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
        $users = User::select('id', 'name')->with('roles:id,name,display_name')->whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->orderBy('name', 'asc')->get()->map(function ($user) {
            $roleName = $user->roles->first()?->display_name ?? 'Admin';
            return [
                'value' => $user->id,
                'label' => $user->name . ' - ' . $roleName
            ];
        });

        $title = "Driving Force";

        return Inertia::render("DrivingForce/Table", compact('dimensions', 'title', 'users'));
    }

    public function fetch_data()
    {
        try {
            $result = $this->drivingForceService->filter(request()->all());

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching driving forces');
            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            DB::rollBack();
            $response = $this->handleError($th, 'creating driving force');
        }

        return response()->json($response, $response['statusCode']);
    }

    public function update(DrivingForceRequest $request, DrivingForce $driving_force)
    {
        $this->authorize('update', $driving_force);

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
            DB::rollBack();
            $response = $this->handleError($th, 'updating driving force');
        }

        return response()->json($response, $response['statusCode']);
    }

    public function delete(DrivingForce $driving_force)
    {
        $this->authorize('delete', $driving_force);

        try {
            DB::beginTransaction();

            $driving_force->delete();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully delete the signal !'
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'deleting driving force');
        }

        return response()->json($response, $response['statusCode']);
    }
}
