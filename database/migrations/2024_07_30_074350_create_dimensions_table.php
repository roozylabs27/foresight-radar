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
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->bigInteger('environment_id')->unsigned();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::table('dimensions', function (Blueprint $table) {
            $table->foreign('environment_id')->references("id")->on('environments')->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimensions');
        Schema::dropIfExists('environments');
    }
};
