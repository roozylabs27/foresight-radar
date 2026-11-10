<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R4.1 (deck slides 2 and 26): the preferred future of an exercise, one quadrant of its
 * approved framework with a rationale, and the milestones back from the target year to now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->foreignId('preferred_quadrant_id')->nullable()->after('focal_suggestion_error')
                ->constrained('scenario_quadrants', indexName: 'scenario_planning_projects_preferred_quadrant_fk')->nullOnDelete();
            $table->text('preferred_rationale')->nullable()->after('preferred_quadrant_id');
            $table->foreignId('preferred_by')->nullable()->after('preferred_rationale')
                ->constrained('users', indexName: 'scenario_planning_projects_preferred_by_fk')->nullOnDelete();
            $table->timestamp('preferred_at')->nullable()->after('preferred_by');
        });

        Schema::create('backcasting_milestones', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('scenario_planning_projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('title', 200);
            $table->text('description')->nullable();
            // planned, in_progress, done
            $table->string('status', 12)->default('planned');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('strategic_response_id')->nullable()->constrained('strategic_responses')->nullOnDelete();
            $table->foreignId('early_warning_indicator_id')->nullable()->constrained('early_warning_indicators')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'year'], 'backcasting_milestones_project_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backcasting_milestones');

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropForeign('scenario_planning_projects_preferred_quadrant_fk');
            $table->dropForeign('scenario_planning_projects_preferred_by_fk');
            $table->dropColumn(['preferred_quadrant_id', 'preferred_rationale', 'preferred_by', 'preferred_at']);
        });
    }
};
