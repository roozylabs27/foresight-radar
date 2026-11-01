<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.5: the conditions of each future (step 5), an ordered list per quadrant of an
 * exercise framework. `origin` / `review_status` follow the R0.6 convention so AI drafts (P2)
 * can arrive as proposals; manual conditions are human and accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_conditions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            $table->string('kind', 20); // pole_expression, force_behaviour, assumption
            $table->string('text', 500);
            $table->char('axis', 1)->nullable(); // A or B, for a pole expression
            $table->foreignId('project_driving_force_id')->nullable()->constrained('project_driving_forces')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('origin', 10)->default('human');
            $table->string('review_status', 10)->default('accepted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scenario_quadrant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_conditions');
    }
};
