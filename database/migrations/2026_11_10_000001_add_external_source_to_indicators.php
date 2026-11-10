<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap R3.6: an indicator with `source_type = external` names a connector, a series and an area
 * (World Bank: indicator code and country). Fetched values become observations with origin
 * `external`, review status `proposed` and their provenance: source, series, period and fetch time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('early_warning_indicators', function (Blueprint $table) {
            $table->string('external_source', 30)->nullable()->after('source_type');
            $table->string('external_series', 60)->nullable()->after('external_source');
            $table->string('external_area', 3)->nullable()->after('external_series');
            $table->string('fetch_status', 20)->nullable()->after('external_area');
            $table->string('fetch_error', 500)->nullable()->after('fetch_status');
            $table->timestamp('last_fetched_at')->nullable()->after('fetch_error');
        });

        Schema::table('indicator_observations', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('review_status');
            $table->string('source_ref', 120)->nullable()->after('source');
            $table->string('source_period', 20)->nullable()->after('source_ref');
            $table->timestamp('fetched_at')->nullable()->after('source_period');

            $table->index(['early_warning_indicator_id', 'source', 'source_period'], 'indicator_observations_source_period');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_observations', function (Blueprint $table) {
            $table->dropIndex('indicator_observations_source_period');
            $table->dropColumn(['source', 'source_ref', 'source_period', 'fetched_at']);
        });

        Schema::table('early_warning_indicators', function (Blueprint $table) {
            $table->dropColumn(['external_source', 'external_series', 'external_area', 'fetch_status', 'fetch_error', 'last_fetched_at']);
        });
    }
};
