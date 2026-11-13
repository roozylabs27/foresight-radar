<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Llm\LlmClientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Production readiness 2026-11-13 (SEC-PR-01, SEC-PR-02): the platform AI key never leaves for a
 * host a user chose, and a user-supplied AI base URL cannot point the server at an internal address.
 */
class LlmCredentialSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM_KEY = 'platform-secret-key-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai_compatible.api_key' => self::PLATFORM_KEY]);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"status":"ok"}']]]])]);
    }

    private function test_connection(array $payload)
    {
        return $this->actingAs(User::factory()->create())->postJson(route('settings.llm.test'), $payload + ['provider' => 'gemini', 'model' => 'gemini-test']);
    }

    private function assertPlatformKeyNeverSent(): void
    {
        Http::assertNotSent(fn (Request $request) => str_contains((string) $request->header('Authorization')[0] ?? '', self::PLATFORM_KEY));
    }

    public function test_the_connection_test_never_sends_the_platform_key_to_a_chosen_host(): void
    {
        $this->test_connection(['base_url' => 'https://attacker.example.com/v1'])->assertStatus(422);

        $this->assertPlatformKeyNeverSent();
    }

    public function test_the_connection_test_never_lends_the_platform_key_even_to_the_default_host(): void
    {
        // A personal test checks the user's own key; without one there is nothing to test.
        $this->test_connection([])->assertStatus(422);

        $this->assertPlatformKeyNeverSent();
    }

    public function test_the_resolver_never_pairs_the_platform_key_with_a_custom_base_url(): void
    {
        $client = app(LlmClientResolver::class)->createClient(['provider' => 'gemini', 'model' => 'gemini-test', 'base_url' => 'https://attacker.example.com/v1']);

        $this->assertFalse($client->isAvailable());
    }

    /** @return array<string, array{string}> */
    public static function internalUrls(): array
    {
        return [
            'plain http' => ['http://api.example.com/v1'],
            'loopback' => ['https://127.0.0.1/v1'],
            'localhost' => ['https://localhost/v1'],
            'private range' => ['https://10.0.0.5/v1'],
            'link-local metadata' => ['https://169.254.169.254/latest'],
            'docker service name' => ['https://laravel_app/v1'],
            'ipv6 loopback' => ['https://[::1]/v1'],
            'not a url' => ['javascript:alert(1)'],
        ];
    }

    /** @dataProvider internalUrls */
    public function test_a_base_url_must_be_a_public_https_address(string $url): void
    {
        $this->test_connection(['base_url' => $url, 'api_key' => 'user-own-key'])->assertJsonValidationErrors('base_url');
        $this->actingAs(User::factory()->create())->postJson(route('settings.llm.store'), [
            'provider' => 'openai_compatible', 'model' => 'm', 'api_key' => 'user-own-key', 'base_url' => $url,
        ])->assertJsonValidationErrors('base_url');

        Http::assertNothingSent();
    }

    public function test_a_public_https_base_url_with_the_users_own_key_is_tested(): void
    {
        $this->test_connection(['base_url' => 'https://api.example.com/v1', 'api_key' => 'user-own-key'])->assertOk();

        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://api.example.com/v1') && $request->header('Authorization')[0] === 'Bearer user-own-key');
    }
}
