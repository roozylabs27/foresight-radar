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
        Schema::create('driving_forces', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->integer('dimension_id');
            $table->integer('time_horizon_id');
            $table->string('keyword', 100);
            $table->string('description', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('time_horizons', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name', 100);
            $table->string('code', 100);
        });

        Schema::create('driving_force_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->integer('driving_force_id');
            $table->integer('status_action_id');
            $table->integer('priority_id');
            $table->unsignedTinyInteger('impact_analysis');
            $table->unsignedTinyInteger('uncertainty_analysis');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('status_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->string('symbol');
        });

        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->string('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driving_forces');
        Schema::dropIfExists('time_horizons');
        Schema::dropIfExists('driving_force_ratings');
        Schema::dropIfExists('status_actions');
        Schema::dropIfExists('priorities');
    }
};
