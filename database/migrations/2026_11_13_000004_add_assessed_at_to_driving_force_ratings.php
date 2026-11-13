<?php

use App\Models\DrivingForceRating;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PI-07 / PAD-1: the FIRM reporting period is "assessed in the period". `assessed_at` is the time of a
 * rating's latest assessment (a change of a scored field), kept by the model. Existing rows take their
 * latest history event (`driving_force_rating_histories.changed_at`), or their creation time when no
 * history exists. Additive and nullable-safe: code that does not know the column keeps working, so a
 * code-only rollback needs no database restore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->timestamp('assessed_at')->nullable()->after('uncertainty_analysis');
            $table->index('assessed_at');
        });

        DrivingForceRating::backfillAssessedAt();
    }

    public function down(): void
    {
        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->dropIndex(['assessed_at']);
            $table->dropColumn('assessed_at');
        });
    }
};
