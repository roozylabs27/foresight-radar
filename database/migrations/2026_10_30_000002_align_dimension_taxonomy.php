<?php

use App\Support\DimensionTaxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eleven-category taxonomy of the scenario planning method (roadmap R0.3).
     * Existing rows are renamed in place (Economy → Economic, Regulation →
     * Legislative, Customer → Consumer, Supplier → Supply Chain), so forces and
     * signals keep their category; Political, Social and New Entrant are added.
     * An empty table (fresh install) is left for the seeder.
     */
    public function up(): void
    {
        Schema::table('dimensions', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->unique()->after('uuid');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
        });

        DimensionTaxonomy::align(createWhenEmpty: false);
    }

    /**
     * Drops the new columns only; renamed and added categories stay.
     */
    public function down(): void
    {
        Schema::table('dimensions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'sort_order']);
        });
    }
};
