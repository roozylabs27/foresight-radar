<?php

namespace App\Providers;

use App\Services\Llm\AnthropicStructuredClient;
use App\Services\Llm\NullLlmClient;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Claude when a key is configured, otherwise a client that is never available.
        $this->app->singleton(StructuredLlmClient::class, function () {
            $config = config('services.anthropic', []);

            return trim((string) ($config['api_key'] ?? '')) !== ''
                ? new AnthropicStructuredClient($config)
                : new NullLlmClient();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        JsonResource::withoutWrapping();
    }
}
