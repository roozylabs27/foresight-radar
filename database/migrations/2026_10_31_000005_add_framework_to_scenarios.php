<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R0.5: a scenario can be the framework (step 4) of an exercise, built on its two approved
 * critical uncertainties, with its own approval (gate G5). Quadrants exist before any narrative, so
 * the narrative is nullable and each quadrant gets a one-line summary. Hard-deleting an axis force
 * no longer deletes the scenario (it keeps the pole labels and shows the axis as deleted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropForeign(['axis_a_force_id']);
            $table->dropForeign(['axis_b_force_id']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->unsignedBigInteger('axis_a_force_id')->nullable()->change();
            $table->unsignedBigInteger('axis_b_force_id')->nullable()->change();
            $table->foreign('axis_a_force_id')->references('id')->on('driving_forces')->nullOnDelete();
            $table->foreign('axis_b_force_id')->references('id')->on('driving_forces')->nullOnDelete();

            // One framework per exercise; legacy scenarios have none.
            $table->foreignId('project_id')->nullable()->unique()->after('uuid')->constrained('scenario_planning_projects')->nullOnDelete();
            $table->foreignId('axis_a_uncertainty_id')->nullable()->after('axis_b_force_id')->constrained('critical_uncertainties')->restrictOnDelete();
            $table->foreignId('axis_b_uncertainty_id')->nullable()->after('axis_a_uncertainty_id')->constrained('critical_uncertainties')->restrictOnDelete();
            $table->string('framework_status', 10)->nullable()->after('status'); // draft, approved; null for legacy scenarios
            $table->foreignId('approved_by')->nullable()->after('framework_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->string('summary', 200)->nullable()->after('title');
            $table->text('narrative')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropColumn('summary');
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['axis_a_uncertainty_id']);
            $table->dropForeign(['axis_b_uncertainty_id']);
            $table->dropForeign(['approved_by']);
            $table->dropUnique(['project_id']);
            $table->dropColumn(['project_id', 'axis_a_uncertainty_id', 'axis_b_uncertainty_id', 'framework_status', 'approved_by', 'approved_at']);
        });
    }
};
