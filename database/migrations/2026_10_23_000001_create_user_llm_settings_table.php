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
        Schema::create('user_llm_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider');
            $table->string('label');
            $table->text('api_key')->nullable();
            $table->string('model');
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_llm_settings');
    }
};
