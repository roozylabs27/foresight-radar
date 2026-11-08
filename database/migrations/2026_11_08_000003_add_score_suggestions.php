<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.4 (score suggestions): AI-proposed scores are `force_assessments` rows of kind
 * `ai_suggestion`, linked to their generation run and the signals they cite. The exercise keeps the
 * state of its latest suggestion job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('force_assessments', function (Blueprint $table) {
            $table->foreignId('generation_run_id')->nullable()->after('origin')->constrained('ai_generation_runs')->nullOnDelete();
            $table->json('cited_signal_ids')->nullable()->after('generation_run_id');
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('score_suggestion_status', 20)->nullable()->after('scores_confirmed_at');
            $table->string('score_suggestion_error', 500)->nullable()->after('score_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['score_suggestion_status', 'score_suggestion_error']);
        });

        Schema::table('force_assessments', function (Blueprint $table) {
            $table->dropForeign(['generation_run_id']);
            $table->dropColumn(['generation_run_id', 'cited_signal_ids']);
        });
    }
};
