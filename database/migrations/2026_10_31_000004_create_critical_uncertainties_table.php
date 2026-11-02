<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roadmap R0.4: critical uncertainties of an exercise. Each groups one or
     * more exercise forces under its own name with two opposing poles and the
     * reason it was chosen; two approved ones, on axis A and B, are gate G4.
     * Members point at project_driving_forces so they stay within the exercise.
     * See docs/audit/SCENARIO_PLANNING_DATA_MODEL_GAP.md §5.4.
     */
    public function up(): void
    {
        Schema::create('critical_uncertainties', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('low_pole_label', 120);
            $table->string('high_pole_label', 120);
            $table->text('selection_rationale');
            // A or B once approved as a scenario axis.
            $table->char('axis', 1)->nullable();
            // proposed, approved, retired
            $table->string('status', 20)->default('proposed');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            // human, ai (R2.4)
            $table->string('origin', 20)->default('human');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('critical_uncertainty_forces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('critical_uncertainty_id')->constrained('critical_uncertainties')->cascadeOnDelete();
            $table->foreignId('project_driving_force_id')->constrained('project_driving_forces')->cascadeOnDelete();

            $table->unique(['critical_uncertainty_id', 'project_driving_force_id'], 'cu_forces_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_uncertainty_forces');
        Schema::dropIfExists('critical_uncertainties');
    }
};
