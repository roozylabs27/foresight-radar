<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

return new class extends Migration
{
    /**
     * Roadmap R1.1: impact and uncertainty scored per exercise (not per tenant),
     * with rationale, plus gate G3 (scores agreed) on the project. One consensus
     * row per exercise force for now; individual and AI-suggested rows come
     * later (R2.1, R2.4). See docs/audit/SCENARIO_PLANNING_DATA_MODEL_GAP.md §5.3.
     */
    public function up(): void
    {
        Schema::create('force_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_driving_force_id')->constrained('project_driving_forces')->cascadeOnDelete();
            // consensus, individual, ai_suggestion
            $table->string('kind', 20)->default('consensus');
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('uncertainty');
            $table->text('impact_rationale')->nullable();
            $table->text('uncertainty_rationale')->nullable();
            // human (scored in the exercise), library (copied from the organization rating), ai
            $table->string('origin', 20)->default('human');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_driving_force_id', 'kind']);
        });

        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->foreignId('scores_confirmed_by')->nullable()->after('forces_confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('scores_confirmed_at')->nullable()->after('scores_confirmed_by');
        });

        // Forces already in an exercise start from their organization rating, marked for review.
        $now = now();
        DB::table('project_driving_forces')
            ->join('driving_force_ratings', 'driving_force_ratings.driving_force_id', '=', 'project_driving_forces.driving_force_id')
            ->whereNull('driving_force_ratings.deleted_at')
            ->whereNotNull('driving_force_ratings.impact_analysis')
            ->whereNotNull('driving_force_ratings.uncertainty_analysis')
            ->get(['project_driving_forces.id', 'project_driving_forces.tenant_id', 'driving_force_ratings.impact_analysis', 'driving_force_ratings.uncertainty_analysis'])
            ->each(fn ($row) => DB::table('force_assessments')->insert([
                'uuid' => (string) Uuid::uuid4(),
                'tenant_id' => $row->tenant_id,
                'project_driving_force_id' => $row->id,
                'kind' => 'consensus',
                'impact' => $row->impact_analysis,
                'uncertainty' => $row->uncertainty_analysis,
                'origin' => 'library',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scores_confirmed_by');
            $table->dropColumn('scores_confirmed_at');
        });
        Schema::dropIfExists('force_assessments');
    }
};
