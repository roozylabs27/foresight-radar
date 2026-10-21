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
        Schema::table('signals', function (Blueprint $table) {
            // "claude" or "heuristic"; null for signals created before this migration.
            $table->string('extraction_provider', 20)->nullable();
            $table->string('extraction_model', 100)->nullable();
            $table->string('prompt_version', 30)->nullable();
            // Whether evidence_quote was found verbatim in the source; null = not checked (older data).
            $table->boolean('quote_verified')->nullable();
            $table->foreignId('suggested_driving_force_id')->nullable()->constrained('driving_forces')->nullOnDelete();
        });

        Schema::table('sources', function (Blueprint $table) {
            // Provider, model, prompt version, token usage and candidate counts of the extraction run.
            $table->json('extraction_meta')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_driving_force_id');
            $table->dropColumn(['extraction_provider', 'extraction_model', 'prompt_version', 'quote_verified']);
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('extraction_meta');
        });
    }
};
