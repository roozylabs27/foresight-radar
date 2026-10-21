<?php

namespace Tests;

use App\Services\Llm\StructuredLlmClient;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeLlmClient;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
