<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R3.1 (focal-issue-v1): an AI critique of the focal question with alternative questions
 * waits on the exercise until it is discarded or the focal question changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->json('focal_suggestion')->nullable()->after('match_suggestion_error');
            $table->string('focal_suggestion_status', 20)->nullable()->after('focal_suggestion');
            $table->string('focal_suggestion_error', 500)->nullable()->after('focal_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['focal_suggestion', 'focal_suggestion_status', 'focal_suggestion_error']);
        });
    }
};
