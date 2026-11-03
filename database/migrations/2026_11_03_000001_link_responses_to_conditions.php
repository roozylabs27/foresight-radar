<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.9: a strategic response names the step 5 conditions it relies on. Changing or removing
 * one of them flags the response as stale (R1.10 pattern) without touching its status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_conditions', function (Blueprint $table) {
            $table->foreignId('strategic_response_id')->constrained('strategic_responses')->cascadeOnDelete();
            $table->foreignId('scenario_condition_id')->constrained('scenario_conditions')->cascadeOnDelete();
            $table->primary(['strategic_response_id', 'scenario_condition_id']);
        });

        Schema::table('strategic_responses', function (Blueprint $table) {
            $table->timestamp('stale_since')->nullable()->after('approved_at');
            $table->string('stale_reason', 500)->nullable()->after('stale_since');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_responses', function (Blueprint $table) {
            $table->dropColumn(['stale_since', 'stale_reason']);
        });

        Schema::dropIfExists('response_conditions');
    }
};
