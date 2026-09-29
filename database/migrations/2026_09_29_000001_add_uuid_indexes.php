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
        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('dimensions', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->unique('uuid');
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('dimensions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
        });
    }
};
