<?php

namespace Tests;

use App\Services\Llm\StructuredLlmClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Tests\Support\FakeLlmClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeVite();
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
