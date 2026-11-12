<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesApiErrors;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use HandlesApiErrors;

    //
    public function index()
    {
        $title = 'User';
        $user = Auth::user();
        if ($user?->hasRole('developer')) {
            $roles = Role::select('id', 'display_name')->get();
        } elseif ($user?->hasRole('super-admin')) {
            $roles = Role::select('id', 'display_name')->whereNotIn('name', ['developer'])->get();
        } else {
            $roles = Role::select('id', 'display_name')->whereNotIn('name', ['super-admin', 'developer'])->get();
        }

        $roles = $roles->map(function ($role) {
            return [
                'value' => $role->id,
                'label' => $role->display_name,
            ];
        });

        return Inertia::render('UserManagement/User/Table', compact('title', 'roles'));
    }

    public function fetch_data()
    {
        try {
            $result = User::filter();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            $error = $this->handleError($th, 'fetching users');

            return response()->json(['errors' => $error['message']], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function create(UserRequest $request)
    {
        // Audit AUD-14: the new user joins the organisation the creator works in (their own, or the
        // workspace an operator picked). Without one the account could not sign in, so refuse.
        $tenantId = app(TenantContext::class)->getTenantId();
        if ($tenantId === null) {
            throw ValidationException::withMessages(['workspace' => 'Pilih organisasi (workspace) dulu sebelum membuat user.']);
        }

        try {
            DB::beginTransaction();

            $new_user = User::create([
                'name' => $request->name,
                'uuid' => Uuid::uuid1(),
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'tenant_id' => $tenantId,
            ]);

            $get_role = Role::where('id', $request->role_id)->firstOrFail();

            $new_user->syncRoles($get_role->name);

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_CREATED,
                'message' => 'Successfully create new user !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'creating user');
        }

        return response()->json($response, $response['statusCode']);
    }

    public function update(UserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        try {
            DB::beginTransaction();

            $user->name = $request['name'];
            $user->email = $request['email'];
            $user->save();

            $user->roles()->detach();

            $get_role = Role::where('id', $request->role_id)->firstOrFail();

            $user->syncRoles($get_role->name);
            $user->syncPermissions([]);

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully update user !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'updating user');
        }

        return response()->json($response, $response['statusCode']);
    }

    public function delete(User $user)
    {
        $this->authorize('delete', $user);

        try {
            DB::beginTransaction();

            $user->delete();

            DB::commit();

            $response = [
                'statusCode' => Response::HTTP_OK,
                'message' => 'Successfully delete the user !',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            $response = $this->handleError($th, 'deleting user');
        }

        return response()->json($response, $response['statusCode']);
    }
}
