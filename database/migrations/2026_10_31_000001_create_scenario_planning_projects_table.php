<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scenario planning exercise (roadmap R0.1): the container for the nine
     * steps, starting with the key focal issue. See
     * docs/audit/SCENARIO_PLANNING_DATA_MODEL_GAP.md §5.1.
     */
    public function up(): void
    {
        Schema::create('scenario_planning_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            // Step 1: the key focal issue.
            $table->text('focal_question');
            $table->text('purpose');
            $table->unsignedSmallInteger('target_year');
            $table->string('geography', 150)->nullable();
            $table->string('industry', 150)->nullable();
            $table->string('owner_unit', 150)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('background')->nullable();
            $table->text('objectives')->nullable();
            // draft, active, monitoring, archived
            $table->string('status', 20)->default('draft');
            // Gate G1: the focal issue was confirmed by this person.
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_planning_projects');
    }
};
