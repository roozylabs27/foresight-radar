<?php

namespace App\Providers;

use App\Services\Llm\AnthropicStructuredClient;
use App\Services\Llm\NullLlmClient;
use App\Services\Llm\OpenAiCompatibleClient;
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
        // The configured provider when it is fully set up, otherwise a client that is never available.
        $this->app->singleton(StructuredLlmClient::class, function () {
            $client = match (config('services.llm.provider', 'anthropic')) {
                'openai_compatible' => new OpenAiCompatibleClient(config('services.openai_compatible', [])),
                default => new AnthropicStructuredClient(config('services.anthropic', [])),
            };

            return $client->isAvailable() ? $client : new NullLlmClient;
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
