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
        Schema::create('tenant_llm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 50)->default('anthropic');
            $table->string('model', 100)->nullable();
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->unsignedInteger('max_tokens')->default(4096);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('allow_user_byok')->default(false);
            $table->unsignedBigInteger('monthly_token_usage')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_llm_settings');
    }
};
