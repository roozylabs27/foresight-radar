<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product integrity PI-10: when each exercise AI assist was last queued ({kind: ISO time}), so a job
 * lost with its worker can be recognised as stale and released (App\Services\Llm\SuggestionRecovery).
 * One JSON column instead of nine timestamps; nullable, nothing to backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->json('suggestion_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn('suggestion_started_at');
        });
    }
};
