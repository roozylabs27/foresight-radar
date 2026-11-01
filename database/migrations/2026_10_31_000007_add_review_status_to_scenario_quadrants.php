<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.6: a quadrant narrative is reviewed before it counts as a decision. Gate G6 is
 * `approved`, with the reviewer and time. Every generation starts the quadrant over as a draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->string('review_status', 10)->default('draft')->after('provider'); // draft, reviewed, approved
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at']);
        });
    }
};
