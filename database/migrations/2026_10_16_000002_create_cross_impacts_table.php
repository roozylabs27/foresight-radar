<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view-cross-impact' => 'permission for access cross-impact matrix page',
        'manage-cross-impact' => 'permission for scoring cross-impact between driving forces',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // How strongly one driving force pushes another: -3 (suppresses) to +3 (amplifies).
        Schema::create('cross_impacts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('source_driving_force_id')->constrained('driving_forces')->cascadeOnDelete();
            $table->foreignId('target_driving_force_id')->constrained('driving_forces')->cascadeOnDelete();
            $table->tinyInteger('score');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_driving_force_id', 'target_driving_force_id'], 'cross_impacts_pair_unique');
            $table->index('target_driving_force_id');
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid1(), 'description' => $description, 'category' => 'cross-impact']
            );
        }

        foreach (['developer', 'super-admin', 'admin'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo(array_keys(self::PERMISSIONS));
        }
        Role::where('name', 'bod')->first()?->givePermissionTo('view-cross-impact');

        // Users carry their role's permissions directly, as in the signal permissions migration.
        foreach (User::with('roles')->get() as $user) {
            $role = $user->roles->first();
            if ($role) {
                $user->givePermissionTo($role->permissions->whereIn('name', array_keys(self::PERMISSIONS)));
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cross_impacts');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
