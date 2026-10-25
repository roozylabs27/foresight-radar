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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('status', 20)->default('active'); // active, suspended, archived
            $table->string('plan', 20)->default('starter'); // starter, professional, enterprise
            $table->unsignedInteger('max_users')->default(20);
            $table->unsignedInteger('storage_quota_mb')->default(5120); // 5GB
            $table->unsignedBigInteger('llm_monthly_token_quota')->default(5000000); // 5M tokens
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
