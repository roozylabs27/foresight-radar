<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-11-11 U3 (AUD-03): a quadrant narrative keeps its history. Before an edit or a
 * regeneration replaces the text, the previous version is stored here with its citations, origin
 * and the run that wrote it, plus who replaced it and why. Rows are only added, never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_narrative_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            // The replaced version.
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('narrative')->nullable();
            $table->json('narrative_statements')->nullable();
            $table->text('implications')->nullable();
            $table->json('early_indicators')->nullable();
            $table->json('cited_signal_ids')->nullable();
            $table->string('origin', 20)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('review_status', 20)->nullable();
            $table->foreignId('generation_run_id')->nullable()->constrained('ai_generation_runs')->nullOnDelete();
            // What replaced it: `edit` or `regenerate`, and by whom.
            $table->string('reason', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['scenario_quadrant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_narrative_revisions');
    }
};
