<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->index('status', 'idx_df_status');
            $table->index(['status', 'created_at'], 'idx_df_status_created');
            $table->index(['dimension_id', 'status'], 'idx_df_dim_status');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->index('created_at', 'idx_dfr_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropIndex('idx_df_status');
            $table->dropIndex('idx_df_status_created');
            $table->dropIndex('idx_df_dim_status');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_dfr_created_at');
        });
    }
};
