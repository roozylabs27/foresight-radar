<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.6 (signal matching): AI may propose observations from new accepted signals. They carry
 * origin `ai` and review status `proposed` and count only once a person confirms them. Existing
 * observations were recorded by people and stay accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_observations', function (Blueprint $table) {
            $table->string('origin', 10)->default('human')->after('note');
            $table->string('review_status', 10)->default('accepted')->after('origin');
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('match_suggestion_status', 20)->nullable()->after('indicator_suggestion_error');
            $table->string('match_suggestion_error', 500)->nullable()->after('match_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['match_suggestion_status', 'match_suggestion_error']);
        });

        Schema::table('indicator_observations', function (Blueprint $table) {
            $table->dropColumn(['origin', 'review_status']);
        });
    }
};
