<?php

use Illuminate\Database\Migrations\Migration;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'manage-members' => 'permission for inviting members, changing member roles, removing members, and cancelling invitations',
        'manage-organization' => 'permission for updating the organization profile and settings',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid1(), 'description' => $description, 'category' => 'organization']
            );
        }

        // Granted through roles only: a direct grant would survive a demotion to bod.
        foreach (['developer', 'super-admin', 'admin'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo(array_keys(self::PERMISSIONS));
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
