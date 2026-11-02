<?php

use App\Services\ScenarioImplicationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.7: opportunities and challenges per quadrant (step 7), linked to exercise forces and
 * to the quadrant's conditions. `implication_links` uses one nullable foreign key per target
 * instead of a polymorphic pair, so the database keeps referential integrity. Existing narrative
 * implications text becomes one untyped proposal per framework quadrant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenario_implications', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('scenario_quadrant_id')->constrained('scenario_quadrants')->cascadeOnDelete();
            $table->string('type', 12)->nullable(); // opportunity, challenge; null for an imported proposal
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('impact_level', 6)->nullable(); // low, medium, high
            $table->unsignedTinyInteger('priority')->nullable(); // 1 = highest
            $table->string('origin', 10)->default('human'); // human, migrated (later: ai)
            $table->string('review_status', 10)->default('accepted'); // proposed, accepted
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('implication_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_implication_id')->constrained('scenario_implications')->cascadeOnDelete();
            $table->foreignId('project_driving_force_id')->nullable()->constrained('project_driving_forces')->cascadeOnDelete();
            $table->foreignId('scenario_condition_id')->nullable()->constrained('scenario_conditions')->cascadeOnDelete();
        });

        ScenarioImplicationService::importNarrativeImplications();
    }

    public function down(): void
    {
        Schema::dropIfExists('implication_links');
        Schema::dropIfExists('scenario_implications');
    }
};
