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
        // State of the queued LLM job: queued, processing, completed or failed.
        // Null for rows written before extraction and generation were queued.
        Schema::table('sources', function (Blueprint $table) {
            $table->string('extraction_status', 12)->nullable();
            $table->text('extraction_error')->nullable();
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->string('generation_status', 12)->nullable();
            // Why the last generation saved nothing (failed), or why it fell back to the template (completed).
            $table->text('generation_error')->nullable();
            $table->text('generation_notice')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['extraction_status', 'extraction_error']);
        });

        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn(['generation_status', 'generation_error', 'generation_notice']);
        });
    }
};
