<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            [
                'name' => 'view-signal',
                'description' => 'permission for access signal ingestion page',
                'category' => 'signal',
            ],
            [
                'name' => 'create-signal',
                'description' => 'permission for ingesting external sources into signals',
                'category' => 'signal',
            ],
            [
                'name' => 'review-signal',
                'description' => 'permission for reviewing and converting signals',
                'category' => 'signal',
            ],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                ['name' => $permData['name'], 'guard_name' => 'web'],
                [
                    'uuid' => (string) Uuid::uuid1(),
                    'description' => $permData['description'],
                    'category' => $permData['category'],
                ]
            );
        }

        $allSignalPermissions = ['view-signal', 'create-signal', 'review-signal'];

        // Assign to developer, super-admin, and admin roles
        foreach (['developer', 'super-admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($allSignalPermissions);
            }
        }

        // Assign view-signal to BOD
        $bodRole = Role::where('name', 'bod')->first();
        if ($bodRole) {
            $bodRole->givePermissionTo('view-signal');
        }

        // Sync permissions to all existing users having these roles
        $users = User::with('roles')->get();
        foreach ($users as $user) {
            if ($user->roles->isNotEmpty()) {
                $roleName = $user->roles->first()->name;
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $user->syncPermissions($role->permissions);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $signalPermissions = ['view-signal', 'create-signal', 'review-signal'];

        foreach ($signalPermissions as $name) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                $permission->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
