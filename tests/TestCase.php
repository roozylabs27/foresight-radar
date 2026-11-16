<?php

namespace Tests;

use App\Services\Llm\StructuredLlmClient;
use App\Support\Net\DnsResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Tests\Support\FakeDnsResolver;
use Tests\Support\FakeLlmClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeVite();
        // No test resolves real DNS names (SSRF guard); tests that need specific answers set them.
        $this->app->instance(DnsResolver::class, new FakeDnsResolver);
    }

    protected function fakeDns(): FakeDnsResolver
    {
        $resolver = $this->app->make(DnsResolver::class);

        return $resolver instanceof FakeDnsResolver ? $resolver : tap(new FakeDnsResolver, fn ($fake) => $this->app->instance(DnsResolver::class, $fake));
    }

    /**
     * Prevent ViteManifestNotFoundException when backend tests render views without compiled assets.
     */
    protected function fakeVite(): void
    {
        $this->app->singleton(Vite::class, function () {
            return new class extends Vite
            {
                public function __invoke($entrypoints, $buildDirectory = null)
                {
                    return new HtmlString('');
                }

                public function reactRefresh()
                {
                    return new HtmlString('');
                }
            };
        });
    }

    /**
     * Swap the LLM for an offline fake and switch signal extraction to it.
     */
    protected function fakeLlm(bool $available = true): FakeLlmClient
    {
        $fake = new FakeLlmClient($available);
        $this->app->instance(StructuredLlmClient::class, $fake);
        config(['services.signal_extraction.driver' => 'llm']);

        return $fake;
    }
}
