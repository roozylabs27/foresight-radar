<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    //
    public function index()
    {
        $title = "User";
        if (auth()->user()->roles->pluck('name')[0] == 'developer') {
            $roles = Role::select('uuid', 'display_name')->get();
        } else if(auth()->user()->roles->pluck('name')[0] == 'super-admin') {
            $roles = Role::select('uuid', 'display_name')->whereNotIn('name', ['developer'])->get();
        } else {
            $roles = Role::select('uuid', 'display_name')->whereNotIn('name', ['super-admin', 'developer'])->get();
        }

        $roles = $roles->map(function ($role) {
            return [
                'value' => $role->uuid,
                'label' => $role->display_name
            ];
        });

        return Inertia::render("UserManagement/User/Table", compact('title', 'roles'));
    }

    public function fetch_data()
    {
        try {
            $result = User::filter();

            return response()->json($result, Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'errors' => $th->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
