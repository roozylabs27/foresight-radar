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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('publisher')->nullable();
            $table->date('published_at')->nullable();
            $table->longText('raw_content');
            $table->string('content_hash', 64);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('content_hash');
        });

        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->bigInteger('source_id')->unsigned();
            $table->bigInteger('dimension_id')->unsigned()->nullable();
            $table->string('title');
            $table->text('summary');
            $table->text('significance')->nullable();
            $table->text('evidence_quote');
            $table->bigInteger('suggested_time_horizon_id')->unsigned()->nullable();
            $table->unsignedTinyInteger('preliminary_impact')->nullable();
            $table->unsignedTinyInteger('preliminary_uncertainty')->nullable();
            $table->decimal('confidence_score', 3, 2)->default(0.85);
            $table->text('confidence_rationale')->nullable();
            $table->boolean('is_ai_generated')->default(true);
            $table->string('review_status', 30)->default('PENDING'); // PENDING, ACCEPTED, REJECTED
            $table->text('rejection_reason')->nullable();
            $table->bigInteger('reviewed_by')->unsigned()->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->bigInteger('created_driving_force_id')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources')->cascadeOnDelete();
            $table->foreign('dimension_id')->references('id')->on('dimensions')->nullOnDelete();
            $table->foreign('suggested_time_horizon_id')->references('id')->on('time_horizons')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_driving_force_id')->references('id')->on('driving_forces')->nullOnDelete();

            $table->index('review_status');
            $table->index(['source_id', 'review_status']);
        });

        Schema::create('signal_driving_force', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('signal_id')->unsigned();
            $table->bigInteger('driving_force_id')->unsigned();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            $table->foreign('driving_force_id')->references('id')->on('driving_forces')->cascadeOnDelete();
            $table->unique(['signal_id', 'driving_force_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_driving_force');
        Schema::dropIfExists('signals');
        Schema::dropIfExists('sources');
    }
};
