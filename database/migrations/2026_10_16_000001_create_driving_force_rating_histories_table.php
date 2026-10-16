<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driving_force_rating_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driving_force_rating_id')->constrained('driving_force_ratings')->cascadeOnDelete();
            $table->foreignId('driving_force_id')->constrained('driving_forces')->cascadeOnDelete();
            $table->string('event', 16);
            // A log keeps the ids it saw, so master data rows are not foreign keys.
            $table->unsignedBigInteger('time_horizon_id')->nullable();
            $table->unsignedBigInteger('status_action_id')->nullable();
            $table->unsignedBigInteger('priority_id')->nullable();
            $table->unsignedTinyInteger('impact_analysis')->nullable();
            $table->unsignedTinyInteger('uncertainty_analysis')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['driving_force_id', 'changed_at']);
        });

        // Ratings that exist before the observer start their history with
        // their current values.
        DB::table('driving_force_rating_histories')->insertUsing(
            [
                'driving_force_rating_id', 'driving_force_id', 'event', 'time_horizon_id', 'status_action_id',
                'priority_id', 'impact_analysis', 'uncertainty_analysis', 'changed_at',
            ],
            DB::table('driving_force_ratings')
                ->whereNull('deleted_at')
                ->select([
                    'id', 'driving_force_id', DB::raw("'baseline'"), 'time_horizon_id', 'status_action_id',
                    'priority_id', 'impact_analysis', 'uncertainty_analysis', DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
                ])
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driving_force_rating_histories');
    }
};
