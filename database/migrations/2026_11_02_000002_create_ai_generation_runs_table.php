<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.12: one row per AI call (signal extraction, scenario narrative, later assists), with
 * provider, model, prompt version, inputs, outcome and usage. What a successful call produced points
 * to its run through `generation_run_id`; output written without AI (keyword rules, template) does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('scenario_planning_projects')->nullOnDelete();
            $table->string('purpose', 40); // signal_extraction, scenario_narrative
            $table->string('provider', 40)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('prompt_version', 30)->nullable();
            $table->json('input_refs')->nullable();
            $table->string('status', 10); // completed, failed, rejected
            $table->string('error', 500)->nullable();
            $table->json('usage')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purpose', 'created_at']);
        });

        foreach (['signals', 'scenarios', 'scenario_quadrants'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('generation_run_id')->nullable()->constrained('ai_generation_runs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['signals', 'scenarios', 'scenario_quadrants'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['generation_run_id']);
                $table->dropColumn('generation_run_id');
            });
        }
        Schema::dropIfExists('ai_generation_runs');
    }
};
