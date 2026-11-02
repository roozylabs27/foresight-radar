<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.8: strategic responses of an exercise (step 8), linked to step 7 items and rated per
 * quadrant. Robustness is derived from `response_scenarios` (count of `applies`), not stored.
 * Separate from the force-level Status of Action (`action_reasons`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategic_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->text('rationale')->nullable();
            $table->text('initiatives')->nullable();
            $table->unsignedTinyInteger('priority')->nullable(); // 1 = highest
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('depends_on')->nullable();
            $table->string('status', 10)->default('proposed'); // proposed, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('origin', 10)->default('human');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('response_implications', function (Blueprint $table) {
            $table->foreignId('strategic_response_id')->constrained('strategic_responses')->cascadeOnDelete();
            $table->foreignId('scenario_implication_id')->constrained('scenario_implications')->cascadeOnDelete();
            $table->primary(['strategic_response_id', 'scenario_implication_id']);
        });

        Schema::create('response_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('strategic_response_id')->constrained('strategic_responses')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            $table->string('fit', 16); // applies, partial, not_applicable
            $table->unique(['strategic_response_id', 'scenario_quadrant_id'], 'response_scenarios_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_scenarios');
        Schema::dropIfExists('response_implications');
        Schema::dropIfExists('strategic_responses');
    }
};
