<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.4 (candidate forces): AI groups accepted signals into candidate driving forces for an
 * exercise. Each candidate is reviewed one by one; accepting creates a PENDING library force,
 * adds it to the exercise and links its signals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('force_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->string('keyword', 100);
            $table->text('description');
            $table->foreignId('dimension_id')->nullable()->constrained('dimensions')->nullOnDelete();
            $table->json('signal_ids');
            $table->text('rationale')->nullable();
            // proposed, accepted, rejected
            $table->string('status', 12)->default('proposed');
            $table->foreignId('generation_run_id')->nullable()->constrained('ai_generation_runs')->nullOnDelete();
            $table->foreignId('driving_force_id')->nullable()->constrained('driving_forces')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('force_suggestion_status', 20)->nullable()->after('score_suggestion_error');
            $table->string('force_suggestion_error', 500)->nullable()->after('force_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['force_suggestion_status', 'force_suggestion_error']);
        });

        Schema::dropIfExists('force_candidates');
    }
};
