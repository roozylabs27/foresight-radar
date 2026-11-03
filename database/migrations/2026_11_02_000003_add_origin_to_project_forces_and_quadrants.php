<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R0.6: the last two exercise tables without `origin` get it (see App\Support\Provenance).
 * Exercise forces are all taken from the library so far; quadrants come from the AI, the template,
 * or the team (framework quadrants before any narrative).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_driving_forces', function (Blueprint $table) {
            $table->string('origin', 20)->default('library')->after('inclusion_status');
        });

        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->string('origin', 20)->default('human')->after('provider');
        });
        DB::table('scenario_quadrants')->where('is_ai_generated', true)->update(['origin' => 'ai']);
        DB::table('scenario_quadrants')->where('is_ai_generated', false)->where('provider', 'template')->update(['origin' => 'template']);
    }

    public function down(): void
    {
        Schema::table('scenario_quadrants', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
        Schema::table('project_driving_forces', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
