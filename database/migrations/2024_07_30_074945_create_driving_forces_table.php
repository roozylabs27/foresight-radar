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
        Schema::create('time_horizons', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name', 100);
            $table->string('code', 100);
            $table->timestamps();
        });

        Schema::create('driving_forces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid();
            $table->bigInteger('dimension_id')->unsigned();
            $table->bigInteger('created_by')->unsigned();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->bigInteger('pic')->unsigned()->nullable();
            $table->string('keyword', 100);
            $table->text('description');
            $table->string('status')->default("PENDING");
            $table->text('remark')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('driving_forces', function (Blueprint $table) {
            $table->foreign('dimension_id')->references("id")->on('dimensions')->onDelete("cascade");
            $table->foreign('created_by')->references("id")->on('users')->onDelete("cascade");
            $table->foreign('updated_by')->references("id")->on('users')->nullOnDelete();
            $table->foreign('pic')->references("id")->on('users')->nullOnDelete();
        });

        Schema::create('status_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->string('symbol');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name');
            $table->string('color');
            $table->timestamps();
        });

        Schema::create('driving_force_ratings', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->bigInteger('driving_force_id')->unsigned();
            $table->bigInteger('time_horizon_id')->unsigned();
            $table->bigInteger('status_action_id')->unsigned()->nullable();
            $table->bigInteger('priority_id')->unsigned()->nullable();
            $table->unsignedTinyInteger('impact_analysis')->nullable();
            $table->unsignedTinyInteger('uncertainty_analysis')->nullable();
            $table->timestamps();
        });

        Schema::create('action_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->bigInteger('driving_force_rating_id')->unsigned();
            $table->bigInteger('status_action_id')->unsigned();
            $table->dateTime('date');
            $table->text('reason');
            $table->timestamps();
        });

        Schema::table('driving_force_ratings', function (Blueprint $table) {
            $table->foreign('driving_force_id')->references("id")->on('driving_forces')->onDelete("cascade");
            $table->foreign('time_horizon_id')->references("id")->on('time_horizons')->onDelete("cascade");
            $table->foreign('status_action_id')->references("id")->on('status_actions')->onDelete("cascade");
            $table->foreign('priority_id')->references("id")->on('priorities')->onDelete("cascade");
        });

        Schema::table('action_reasons', function(Blueprint $table) {
            $table->foreign('driving_force_rating_id')->references("id")->on('driving_force_ratings')->restrictOnDelete();
            $table->foreign('status_action_id')->references("id")->on('status_actions')->restrictOnDelete();
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
        Schema::dropIfExists('action_reasons');
        Schema::dropIfExists('status_actions');
        Schema::dropIfExists('priorities');
    }
};
