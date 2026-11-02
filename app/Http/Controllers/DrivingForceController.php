<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\DrivingForceRequest;
use App\Models\Dimension;
use App\Models\DrivingForce;
use App\Models\User;
use App\Services\DrivingForceMergeService;
use App\Services\DrivingForceService;
use App\Services\TenantContext;
use App\Support\TenantRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class DrivingForceController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected DrivingForceService $drivingForceService) {}

    public function index()
    {
        $dimensions = Dimension::ordered()->get(['id', 'name'])->map(function ($dimension) {
            return [
                'value' => $dimension->id,
                'label' => $dimension->name,
            ];
        });
        // User has no tenant scope, so filter by hand. Only the platform bypass has no tenant id.
        $tenantId = app(TenantContext::class)->getTenantId();
        $users = User::select('id', 'name')->with('roles:id,name,display_name')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('roles', function ($q) {
                $q->where('name', 'admin');
            })->orderBy('name', 'asc')->get()->map(function ($user) {
                $roleName = $user->roles->first()?->display_name ?? 'Admin';

                return [
                    'value' => $user->id,
                    'label' => $user->name.' - '.$roleName,
                ];
            });

        $title = 'Driving Force';
        // Merge targets (roadmap R1.3): live forces only; merged tombstones are soft deleted.
        $forceOptions = DrivingForce::orderBy('keyword')->get(['uuid', 'keyword'])
            ->map(fn ($force) => ['value' => $force->uuid, 'label' => $force->keyword]);

        return Inertia::render('DrivingForce/Table', compact('dimensions', 'title', 'users', 'forceOptions'));
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
            $validated = $request->validated();

            DrivingForce::create([
                'uuid' => Uuid::uuid1(),
                'dimension_id' => $validated['dimension_id'],
                'pic' => $validated['pic_id'],
                'created_by' => Auth::id(),
                'keyword' => $validated['keyword'],
                'description' => $validated['description'],
                'status' => 'PENDING',
            ]);

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_CREATED,
                'message' => 'Successfully create new signal !',
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

            $validated = $request->validated();

            $driving_force->dimension_id = $validated['dimension_id'];
            $driving_force->updated_by = Auth::id();
            $driving_force->pic = $validated['pic_id'];
            $driving_force->keyword = $validated['keyword'];
            $driving_force->description = $validated['description'];
            $driving_force->save();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully update the signal !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'updating driving force');
        }

        return response()->json($response, $response['statusCode']);
    }

    /**
     * Merge this duplicate into another force (roadmap R1.3). The duplicate becomes a tombstone.
     */
    public function merge(Request $request, DrivingForce $driving_force, DrivingForceMergeService $merges): JsonResponse
    {
        $validated = $request->validate(['target' => ['required', 'uuid', TenantRule::exists('driving_forces', 'uuid')]], [], ['target' => 'driving force tujuan']);
        $target = DrivingForce::withTrashed()->where('uuid', $validated['target'])->firstOrFail();

        $merges->merge($driving_force, $target, $request->user());

        return response()->json(['message' => "\"{$driving_force->keyword}\" digabung ke \"{$target->keyword}\"."], Response::HTTP_OK);
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
                'message' => 'Successfully delete the signal !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'deleting driving force');
        }

        return response()->json($response, $response['statusCode']);
    }
}
