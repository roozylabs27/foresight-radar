<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R2.8 slice 2: the team's consistency review of each quadrant. The status is cleared
 * when an accepted condition of the quadrant changes; the reviewer and date stay, so the page can
 * ask for a new review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->string('consistency_status', 12)->nullable()->after('stale_reason'); // consistent, issues
            $table->string('consistency_note', 1000)->nullable()->after('consistency_status');
            $table->foreignId('consistency_reviewed_by')->nullable()->after('consistency_note')
                ->constrained('users', 'id', 'scenario_quadrants_consistency_reviewer_fk')->nullOnDelete();
            $table->timestamp('consistency_reviewed_at')->nullable()->after('consistency_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropForeign('scenario_quadrants_consistency_reviewer_fk');
            $table->dropColumn(['consistency_status', 'consistency_note', 'consistency_reviewed_by', 'consistency_reviewed_at']);
        });
    }
};
