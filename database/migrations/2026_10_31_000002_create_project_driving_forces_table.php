<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roadmap R0.2: the driving forces of one exercise, picked from the tenant's
     * library, with what they mean for this exercise; plus gate G2 (force list
     * agreed) on the project. See docs/audit/SCENARIO_PLANNING_DATA_MODEL_GAP.md §5.1.
     */
    public function up(): void
    {
        Schema::create('project_driving_forces', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->foreignId('driving_force_id')->constrained('driving_forces')->cascadeOnDelete();
            // proposed (e.g. by AI later), included, excluded
            $table->string('inclusion_status', 20)->default('included');
            $table->text('influence_note')->nullable();
            // High impact but low uncertainty: holds in every scenario, never an axis.
            $table->boolean('is_predetermined')->default(false);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'driving_force_id']);
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->foreignId('forces_confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('forces_confirmed_at')->nullable()->after('forces_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forces_confirmed_by');
            $table->dropColumn('forces_confirmed_at');
        });
        Schema::dropIfExists('project_driving_forces');
    }
};
