<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product integrity roadmap #13: each decision keeps the artefact it is about (type and id) and the
 * reason the team gave (optional; required when a warning is overridden). Existing rows stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_decisions', function (Blueprint $table) {
            $table->string('artefact_type', 40)->nullable();
            $table->unsignedBigInteger('artefact_id')->nullable();
            $table->string('rationale', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('project_decisions', function (Blueprint $table) {
            $table->dropColumn(['artefact_type', 'artefact_id', 'rationale']);
        });
    }
};
