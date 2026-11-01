<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.10: an upstream change flags a framework and its quadrants as stale, with the reason.
 * Nothing is deleted or regenerated; "Keep" records who acknowledged it and clears the flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->timestamp('stale_since')->nullable()->after('approved_at');
            $table->string('stale_reason', 500)->nullable()->after('stale_since');
            $table->foreignId('stale_acknowledged_by')->nullable()->after('stale_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('stale_acknowledged_at')->nullable()->after('stale_acknowledged_by');
        });

        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->timestamp('stale_since')->nullable()->after('reviewed_at');
            $table->string('stale_reason', 500)->nullable()->after('stale_since');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropColumn(['stale_since', 'stale_reason']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropForeign(['stale_acknowledged_by']);
            $table->dropColumn(['stale_since', 'stale_reason', 'stale_acknowledged_by', 'stale_acknowledged_at']);
        });
    }
};
