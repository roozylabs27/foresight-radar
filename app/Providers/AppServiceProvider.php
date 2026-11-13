<?php

namespace App\Providers;

use App\Services\Llm\AnthropicStructuredClient;
use App\Services\Llm\NullLlmClient;
use App\Services\Llm\OpenAiCompatibleClient;
use App\Services\Llm\QuotaLimitedLlmClient;
use App\Services\Llm\StructuredLlmClient;
use App\Services\TenantContext;
use App\Support\DecisionContext;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped, not singleton: queue:work forgets scoped instances before every job,
        // so one job's tenant can never carry over into the next.
        $this->app->scoped(TenantContext::class, function () {
            return new TenantContext;
        });
        // Roadmap #13: the reason given for a gate decision in this request (scoped the same way).
        $this->app->scoped(DecisionContext::class, fn () => new DecisionContext);

        // The configured provider when it is fully set up, otherwise a client that is never available.
        $this->app->singleton(StructuredLlmClient::class, function () {
            $client = match (config('services.llm.provider', 'anthropic')) {
                'openai_compatible' => new OpenAiCompatibleClient(config('services.openai_compatible', [])),
                default => new AnthropicStructuredClient(config('services.anthropic', [])),
            };

            // Calls on the platform key count against the calling tenant's monthly quota (TENANT-008).
            return QuotaLimitedLlmClient::wrap($client->isAvailable() ? $client : new NullLlmClient);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        JsonResource::withoutWrapping();
    }
}
