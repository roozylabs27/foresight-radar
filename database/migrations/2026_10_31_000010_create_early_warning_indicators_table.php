<?php

use App\Services\EarlyWarningIndicatorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.9: the early warning indicator register of an exercise (step 9) and the expected
 * direction of each indicator in each quadrant. Links point at the exercise's own critical
 * uncertainty or force. Observations (`indicator_observations`) are P2. Indicator strings that
 * narratives already wrote become proposals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('early_warning_indicators', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->foreignId('critical_uncertainty_id')->nullable()->constrained('critical_uncertainties')->nullOnDelete();
            $table->foreignId('project_driving_force_id')->nullable()->constrained('project_driving_forces')->nullOnDelete();
            $table->string('metric', 120)->nullable();
            $table->string('unit', 40)->nullable();
            $table->decimal('baseline_value', 18, 4)->nullable();
            $table->date('baseline_date')->nullable();
            $table->string('frequency', 10)->default('ad_hoc'); // monthly, quarterly, ad_hoc
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 12)->default('manual'); // manual, signal_feed, external
            $table->string('threshold_rule', 500)->nullable();
            $table->string('status', 10)->default('active'); // active, retired
            $table->string('origin', 10)->default('human'); // human, migrated
            $table->string('review_status', 10)->default('accepted'); // proposed, accepted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('indicator_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('early_warning_indicator_id')->constrained('early_warning_indicators')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            $table->string('expected_direction', 12); // rising, falling, stable, qualitative
            $table->string('note', 300)->nullable();
            $table->unique(['early_warning_indicator_id', 'scenario_quadrant_id'], 'indicator_scenarios_unique');
        });

        EarlyWarningIndicatorService::importNarrativeIndicators();
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_scenarios');
        Schema::dropIfExists('early_warning_indicators');
    }
};
