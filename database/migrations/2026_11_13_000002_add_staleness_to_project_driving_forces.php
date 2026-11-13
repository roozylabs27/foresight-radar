<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product integrity PI-06: an exercise force is flagged when its firm library force is edited,
 * rejected, closed or deleted (App\Observers\DrivingForceObserver). Nullable, nothing to backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_driving_forces', function (Blueprint $table) {
            $table->timestamp('stale_since')->nullable();
            $table->string('stale_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_driving_forces', function (Blueprint $table) {
            $table->dropColumn(['stale_since', 'stale_reason']);
        });
    }
};
