<?php

namespace Tests\Feature\Llm;

use App\Services\Llm\StructuredLlmClient;
use Tests\TestCase;

/**
 * The browser E2E stack's scripted AI client (LLM_PROVIDER=e2e) must never be active anywhere else:
 * a misconfigured production .env would otherwise answer every AI assist with canned text.
 */
class E2eScriptedClientGuardTest extends TestCase
{
    public function test_the_e2e_provider_is_unavailable_outside_the_e2e_environment(): void
    {
        config(['services.llm.provider' => 'e2e']);
        $this->app->forgetInstance(StructuredLlmClient::class);

        $client = $this->app->make(StructuredLlmClient::class);

        $this->assertFalse($client->isAvailable());
        $this->assertNotSame('e2e', $client->provider());
    }

    public function test_in_the_e2e_environment_it_answers_the_focal_issue_assist_only(): void
    {
        $this->app['env'] = 'e2e';
        config(['services.llm.provider' => 'e2e']);
        $this->app->forgetInstance(StructuredLlmClient::class);

        $client = $this->app->make(StructuredLlmClient::class);

        $this->assertTrue($client->isAvailable());
        $this->assertSame('e2e', $client->provider());
    }
}
