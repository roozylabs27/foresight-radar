<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Roadmap R2.6: state of the latest AI strategic response job of an exercise. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->string('response_suggestion_status', 20)->nullable()->after('implication_suggestion_error');
            $table->string('response_suggestion_error', 500)->nullable()->after('response_suggestion_status');
        });
    }

    public function down(): void
    {
        Schema::table('scenario_planning_projects', function (Blueprint $table) {
            $table->dropColumn(['response_suggestion_status', 'response_suggestion_error']);
        });
    }
};
