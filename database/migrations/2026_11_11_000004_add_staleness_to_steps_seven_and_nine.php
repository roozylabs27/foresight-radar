<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit 2026-11-11 U6 (AUD-07): implications (Step 7) and early warning indicators (Step 9) can be
 * flagged for review when upstream work changes, like quadrants and strategic responses already are.
 */
return new class extends Migration
{
    private const TABLES = ['scenario_implications', 'early_warning_indicators'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('stale_since')->nullable();
                $table->string('stale_reason', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['stale_since', 'stale_reason']));
        }
    }
};
