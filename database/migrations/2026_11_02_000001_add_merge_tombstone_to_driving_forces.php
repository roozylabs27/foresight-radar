<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R1.3: a duplicate driving force merged into another keeps a tombstone (soft deleted,
 * pointing at the surviving force) so old references can be explained and audited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('closed_at')->constrained('driving_forces')->nullOnDelete();
            $table->foreignId('merged_by')->nullable()->after('merged_into_id')->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_by');
        });
    }

    public function down(): void
    {
        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropForeign(['merged_by']);
            $table->dropColumn(['merged_into_id', 'merged_by', 'merged_at']);
        });
    }
};
