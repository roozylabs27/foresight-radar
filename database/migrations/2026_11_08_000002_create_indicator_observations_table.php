<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.3 (observation log): what an early warning indicator showed on a date, recorded and
 * confirmed by a person, optionally evidenced by an accepted signal. `direction` feeds the
 * relevance view; `crossed_threshold` the review trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_observations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('early_warning_indicator_id')->constrained('early_warning_indicators')->cascadeOnDelete();
            $table->date('observed_at');
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('value_text', 255)->nullable();
            // rising, falling, stable
            $table->string('direction', 12)->nullable();
            $table->boolean('crossed_threshold')->default(false);
            $table->foreignId('signal_id')->nullable()->constrained('signals')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['early_warning_indicator_id', 'observed_at'], 'indicator_observations_indicator_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_observations');
    }
};
