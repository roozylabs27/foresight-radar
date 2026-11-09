<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.5 (framework-v1): an AI suggestion of pole labels and quadrant names waits on the
 * draft framework until the coordinator applies or discards it; the exercise keeps the job state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->json('framework_suggestion')->nullable()->after('framework_status');
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('framework_suggestion_status', 20)->nullable()->after('condition_suggestion_error');
            $table->string('framework_suggestion_error', 500)->nullable()->after('framework_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['framework_suggestion_status', 'framework_suggestion_error']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn('framework_suggestion');
        });
    }
};
