<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-11-11 U4 (AUD-04, AUD-05): a numeric threshold is an operator and a value, and the
 * server decides a crossing. `threshold_rule` stays as the human note. Each crossing is recorded
 * once per observation; deleting the observation closes the event and keeps it as history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('early_warning_indicators', function (Blueprint $table) {
            // gt, gte, lt, lte; null for a qualitative indicator
            $table->string('threshold_operator', 3)->nullable()->after('threshold_rule');
            $table->decimal('threshold_value', 20, 6)->nullable()->after('threshold_operator');
        });

        Schema::create('indicator_threshold_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('early_warning_indicator_id')->constrained('early_warning_indicators')->cascadeOnDelete();
            $table->foreignId('indicator_observation_id')->nullable()->unique()->constrained('indicator_observations')->nullOnDelete();
            // numeric: decided by the server; manual: a person flagged a qualitative indicator, with a note
            $table->string('kind', 10);
            $table->string('operator', 3)->nullable();
            $table->decimal('threshold_value', 20, 6)->nullable();
            $table->decimal('observed_value', 20, 6)->nullable();
            $table->date('observed_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Explicit name: the default one is longer than MySQL's 64 characters.
            $table->index(['early_warning_indicator_id', 'observed_at'], 'threshold_events_indicator_observed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_threshold_events');
        Schema::table('early_warning_indicators', function (Blueprint $table) {
            $table->dropColumn(['threshold_operator', 'threshold_value']);
        });
    }
};
