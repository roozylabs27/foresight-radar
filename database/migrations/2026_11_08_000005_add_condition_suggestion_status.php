<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Roadmap R2.5: state of the latest AI condition draft job of an exercise. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('condition_suggestion_status', 20)->nullable()->after('force_suggestion_error');
            $table->string('condition_suggestion_error', 500)->nullable()->after('condition_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['condition_suggestion_status', 'condition_suggestion_error']);
        });
    }
};
