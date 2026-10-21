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
        'view-scenario' => 'permission for access scenario matrix page',
        'manage-scenario' => 'permission for creating scenarios and generating narratives',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A 2x2 scenario matrix: two driving forces as axes, each with a low and a high pole.
        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->text('focal_question')->nullable();
            $table->foreignId('axis_a_force_id')->constrained('driving_forces')->cascadeOnDelete();
            $table->foreignId('axis_b_force_id')->constrained('driving_forces')->cascadeOnDelete();
            $table->string('axis_a_low_label');
            $table->string('axis_a_high_label');
            $table->string('axis_b_low_label');
            $table->string('axis_b_high_label');
            $table->string('status', 10)->default('DRAFT'); // DRAFT, FINAL
            // Provenance of the last generation: "claude" or "template".
            $table->string('provider', 20)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 30)->nullable();
            $table->json('input_signal_ids')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('scenario_quadrants', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('scenario_id')->constrained('scenarios')->cascadeOnDelete();
            // First letter: axis A pole, second: axis B pole (H = high, L = low).
            $table->string('position', 2);
            $table->string('title');
            $table->text('narrative');
            $table->text('implications')->nullable();
            $table->json('early_indicators')->nullable();
            $table->json('cited_signal_ids')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->string('provider', 20)->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['scenario_id', 'position'], 'scenario_quadrants_position_unique');
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['uuid' => (string) Uuid::uuid1(), 'description' => $description, 'category' => 'scenario']
            );
        }

        foreach (['developer', 'super-admin', 'admin'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo(array_keys(self::PERMISSIONS));
        }
        Role::where('name', 'bod')->first()?->givePermissionTo('view-scenario');

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
        Schema::dropIfExists('scenario_quadrants');
        Schema::dropIfExists('scenarios');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::PERMISSIONS))->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
