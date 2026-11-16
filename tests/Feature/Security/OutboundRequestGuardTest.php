<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Llm\LlmClientResolver;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\OpenAiCompatibleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Production readiness 2026-11-13: the AI client built from user or organisation settings is
 * SSRF-guarded at request time (not only when the URL is saved): DNS is checked per request,
 * redirects are not followed, and the platform key never leaves for a custom host.
 */
class OutboundRequestGuardTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $baseUrl, string $key = 'user-own-key')
    {
        return app(LlmClientResolver::class)->createClient(['provider' => 'openai_compatible', 'model' => 'm', 'api_key' => $key, 'base_url' => $baseUrl]);
    }

    private function request(): LlmRequest
    {
        return new LlmRequest(purpose: 'connection_test', system: 's', user: 'u', schema: ['type' => 'object', 'properties' => ['status' => ['type' => 'string']], 'required' => ['status'], 'additionalProperties' => false], maxTokens: 64);
    }

    private function ok(): array
    {
        return ['choices' => [['message' => ['content' => '{"status":"ok"}'], 'finish_reason' => 'stop']]];
    }

    public function test_a_public_custom_host_works(): void
    {
        Http::fake(['*' => Http::response($this->ok())]);

        $response = $this->client('https://api.provider.example/v1')->generateJson($this->request());

        $this->assertSame(['status' => 'ok'], $response->data);
        Http::assertSentCount(1);
        $this->assertSame(['api.provider.example'], $this->fakeDns()->queries);
    }

    /** A saved URL whose name later resolves to an internal address (DNS rebinding) is refused at request time. */
    public function test_a_custom_host_that_now_resolves_internally_is_refused_before_any_request(): void
    {
        Http::fake(['*' => Http::response($this->ok())]);
        $this->fakeDns()->answer('api.provider.example', ['169.254.169.254']);

        try {
            $this->client('https://api.provider.example/v1')->generateJson($this->request());
            $this->fail('The internal address was called');
        } catch (LlmException $e) {
            $this->assertSame('api_error', $e->reason);
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function redirectTargets(): array
    {
        return [
            'localhost' => ['http://localhost/admin'],
            'private ip' => ['https://10.0.0.5/v1/chat/completions'],
            'metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'public host (chain start)' => ['https://other.provider.example/v1/chat/completions'],
        ];
    }

    /** @dataProvider redirectTargets */
    public function test_redirects_are_never_followed(string $location): void
    {
        Http::fake([
            'api.provider.example/*' => Http::response('', 302, ['Location' => $location]),
            '*' => Http::response($this->ok()),
        ]);

        try {
            $this->client('https://api.provider.example/v1')->generateJson($this->request());
            $this->fail('The redirect was followed');
        } catch (LlmException $e) {
            $this->assertSame([302, 'api_error'], [$e->status, $e->reason]);
        }
        // Only the first request went out: the redirect (and any chain behind it) was not followed.
        Http::assertSentCount(1);
    }

    public function test_the_connection_test_reports_an_internal_resolution_without_sending_the_key(): void
    {
        Http::fake(['*' => Http::response($this->ok())]);
        $this->fakeDns()->answer('api.provider.example', ['10.0.0.8']);

        $this->actingAs(User::factory()->create())
            ->postJson(route('settings.llm.test'), ['provider' => 'openai_compatible', 'model' => 'm', 'api_key' => 'user-own-key', 'base_url' => 'https://api.provider.example/v1'])
            ->assertJsonValidationErrors('base_url');

        Http::assertNothingSent();
    }

    public function test_saving_a_url_whose_name_resolves_internally_is_refused(): void
    {
        $this->fakeDns()->answer('intranet-llm.example.com', ['192.168.10.20']);

        $this->actingAs(User::factory()->create())->postJson(route('settings.llm.store'), [
            'provider' => 'openai_compatible', 'model' => 'm', 'api_key' => 'user-own-key', 'base_url' => 'https://intranet-llm.example.com/v1',
        ])->assertJsonValidationErrors('base_url');
    }

    public function test_the_platform_client_from_env_is_not_guarded(): void
    {
        // The operator's own .env may point at a local model server; only user and organisation URLs are guarded.
        $this->fakeDns()->answer('api.provider.example', ['10.0.0.8']);
        Http::fake(['*' => Http::response($this->ok())]);
        $client = new OpenAiCompatibleClient(['base_url' => 'https://api.provider.example/v1', 'model' => 'm', 'api_key' => 'k']);

        $this->assertSame(['status' => 'ok'], $client->generateJson($this->request())->data);
    }
}
