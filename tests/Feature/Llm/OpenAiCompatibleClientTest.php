<?php

namespace Tests\Feature\Llm;

use App\Models\Dimension;
use App\Models\Environment;
use App\Models\Signal;
use App\Models\TimeHorizon;
use App\Models\User;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\NullLlmClient;
use App\Services\Llm\OpenAiCompatibleClient;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The OpenAI-compatible adapter against faked HTTP responses: no network.
 */
class OpenAiCompatibleClientTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://llm.test/api/v1';

    private function config(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'openrouter',
            'label' => 'OpenRouter',
            'base_url' => self::BASE,
            'api_key' => 'sk-or-test-not-real',
            'model' => 'vendor/model-x',
            'structured_output' => 'json_schema',
            'timeout' => 30,
            'max_retries' => 1,
            'retry_delay_ms' => 0,
        ];
    }

    private function request(): LlmRequest
    {
        return new LlmRequest('signal_extraction', 'SYSTEM PROMPT', 'USER DOCUMENT', [
            'type' => 'object',
            'properties' => ['ok' => ['type' => 'boolean']],
            'required' => ['ok'],
            'additionalProperties' => false,
        ], 4000, 'medium');
    }

    private function completion(string $content, string $finish = 'stop', array $message = []): array
    {
        return [
            'model' => 'vendor/model-x-2026',
            'choices' => [['finish_reason' => $finish, 'message' => $message + ['role' => 'assistant', 'content' => $content]]],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 40, 'prompt_tokens_details' => ['cached_tokens' => 512]],
        ];
    }

    public function test_sends_a_strict_json_schema_request_and_decodes_the_answer(): void
    {
        Http::fake([self::BASE.'/chat/completions' => Http::response($this->completion('{"ok": true}'))]);

        $response = (new OpenAiCompatibleClient($this->config()))->generateJson($this->request());

        $this->assertSame(['ok' => true], $response->data);
        $this->assertSame('openrouter', $response->provider);
        $this->assertSame('vendor/model-x-2026', $response->model);
        $this->assertSame(['input_tokens' => 900, 'output_tokens' => 40, 'cache_read_input_tokens' => 512], $response->usage);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === self::BASE.'/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-or-test-not-real')
                && $request->hasHeader('X-Title', 'Foresight Radar') === false
                && $body['model'] === 'vendor/model-x'
                && $body['max_tokens'] === 4000
                && $body['messages'] === [
                    ['role' => 'system', 'content' => 'SYSTEM PROMPT'],
                    ['role' => 'user', 'content' => 'USER DOCUMENT'],
                ]
                && $body['response_format']['type'] === 'json_schema'
                && $body['response_format']['json_schema']['strict'] === true
                && $body['response_format']['json_schema']['name'] === 'signal_extraction'
                && $body['response_format']['json_schema']['schema']['required'] === ['ok']
                && ! array_key_exists('reasoning_effort', $body);
        });
    }

    public function test_reasoning_effort_is_sent_only_when_configured(): void
    {
        Http::fake([self::BASE.'/chat/completions' => Http::response($this->completion('{"ok": true}'))]);

        (new OpenAiCompatibleClient($this->config(['reasoning_effort' => 'low'])))->generateJson($this->request());

        Http::assertSent(fn (Request $request) => $request->data()['reasoning_effort'] === 'low');
    }

    public function test_json_object_mode_puts_the_schema_in_the_user_message(): void
    {
        Http::fake([self::BASE.'/chat/completions' => Http::response($this->completion("```json\n{\"ok\": false}\n```"))]);

        $response = (new OpenAiCompatibleClient($this->config(['structured_output' => 'json_object'])))->generateJson($this->request());

        // A fenced answer is still read.
        $this->assertSame(['ok' => false], $response->data);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $body['response_format'] === ['type' => 'json_object']
                && str_starts_with($body['messages'][1]['content'], 'USER DOCUMENT')
                && str_contains($body['messages'][1]['content'], '"required":["ok"]')
                && $body['messages'][0]['content'] === 'SYSTEM PROMPT';
        });
    }

    public function test_stop_reasons_and_bad_content_map_to_reasons(): void
    {
        $client = new OpenAiCompatibleClient($this->config());
        $cases = [
            'max_tokens' => $this->completion('{"ok":', 'length'),
            'refusal' => $this->completion('', 'stop', ['refusal' => 'I cannot help with that.']),
            'invalid_json' => $this->completion('Sure! Here it is: {ok}'),
        ];
        $cases['content_filter'] = $this->completion('', 'content_filter');

        foreach ($cases as $expected => $completion) {
            try {
                $client->toResponse($completion, 'test', 5);
                $this->fail("Expected {$expected}");
            } catch (LlmException $e) {
                $this->assertSame($expected === 'content_filter' ? 'refusal' : $expected, $e->reason);
            }
        }
    }

    public function test_http_errors_map_to_reasons_after_retrying_retryable_ones(): void
    {
        $client = new OpenAiCompatibleClient($this->config());
        $cases = [429 => 'rate_limited', 503 => 'overloaded', 500 => 'api_error', 400 => 'api_error', 401 => 'api_error'];

        // Retryable statuses (429, 5xx) are sent twice with max_retries = 1; others once.
        $sequence = Http::fakeSequence(self::BASE.'/chat/completions');
        foreach ([429, 429, 503, 503, 500, 500, 400, 401] as $status) {
            $sequence->push(['error' => ['message' => 'nope']], $status);
        }

        foreach ($cases as $status => $reason) {
            try {
                $client->generateJson($this->request());
                $this->fail("Expected {$reason} for {$status}");
            } catch (LlmException $e) {
                $this->assertSame($reason, $e->reason, "status {$status}");
            }
        }
        Http::assertSentCount(8);
    }

    public function test_rate_limit_is_retried_once_then_succeeds(): void
    {
        Http::fakeSequence(self::BASE.'/chat/completions')
            ->push(['error' => ['message' => 'slow down']], 429)
            ->push($this->completion('{"ok": true}'));

        $response = (new OpenAiCompatibleClient($this->config()))->generateJson($this->request());

        $this->assertSame(['ok' => true], $response->data);
        Http::assertSentCount(2);
    }

    public function test_logs_usage_but_never_prompt_document_or_key(): void
    {
        Log::spy();
        Http::fake([self::BASE.'/chat/completions' => Http::response($this->completion('{"ok": true}'))]);

        (new OpenAiCompatibleClient($this->config()))->generateJson($this->request());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $encoded = json_encode($context);

            return $message === 'llm.call'
                && $context['provider'] === 'openrouter'
                && $context['usage']['input_tokens'] === 900
                && ! str_contains($encoded, 'USER DOCUMENT')
                && ! str_contains($encoded, 'SYSTEM PROMPT')
                && ! str_contains($encoded, 'sk-or-test');
        })->once();
    }

    public function test_availability_needs_base_url_model_and_a_key_unless_local(): void
    {
        $this->assertTrue((new OpenAiCompatibleClient($this->config()))->isAvailable());
        $this->assertFalse((new OpenAiCompatibleClient($this->config(['model' => ''])))->isAvailable());
        $this->assertFalse((new OpenAiCompatibleClient($this->config(['api_key' => ''])))->isAvailable());
        $this->assertTrue((new OpenAiCompatibleClient($this->config(['api_key' => '', 'allow_no_key' => true, 'name' => 'ollama', 'label' => ''])))->isAvailable());
        $this->assertSame('Ollama', (new OpenAiCompatibleClient($this->config(['name' => 'ollama', 'label' => ''])))->label());
    }

    public function test_container_picks_the_configured_provider(): void
    {
        config(['services.llm.provider' => 'openai_compatible', 'services.openai_compatible' => $this->config()]);
        $this->app->forgetInstance(StructuredLlmClient::class);
        $this->assertInstanceOf(OpenAiCompatibleClient::class, $this->app->make(StructuredLlmClient::class));

        config(['services.openai_compatible.model' => '']);
        $this->app->forgetInstance(StructuredLlmClient::class);
        $this->assertInstanceOf(NullLlmClient::class, $this->app->make(StructuredLlmClient::class));
    }

    public function test_ingest_through_an_openai_compatible_provider_records_its_provenance(): void
    {
        config([
            'services.llm.provider' => 'openai_compatible',
            'services.openai_compatible' => $this->config(),
            'services.signal_extraction.driver' => 'llm',
        ]);
        $this->app->forgetInstance(StructuredLlmClient::class);

        foreach (['view-signal', 'create-signal'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['uuid' => (string) Uuid::uuid4(), 'category' => 'signal', 'description' => $name]);
        }
        $analyst = User::factory()->create();
        $analyst->givePermissionTo(['view-signal', 'create-signal']);
        $environment = Environment::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Macro']);
        Dimension::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Regulation', 'environment_id' => $environment->id]);
        TimeHorizon::create(['uuid' => (string) Uuid::uuid4(), 'name' => 'Mid Term', 'code' => 'MT']);

        $document = 'The Ministry of Industry enacted a regulation requiring 40 percent local content for electric vehicles sold after 2027.';
        Http::fake([self::BASE.'/chat/completions' => Http::response($this->completion(json_encode(['signals' => [[
            'title' => 'Local Content Mandate',
            'summary' => 'EVs need 40 percent local content after 2027.',
            'significance' => 'Sourcing must change.',
            'evidence_quote' => $document,
            'dimension' => 'Regulation',
            'time_horizon' => 'Mid Term',
            'impact' => 8,
            'uncertainty' => 2,
            'confidence' => 0.9,
            'confidence_rationale' => 'Enacted.',
            'related_force_ref' => 'none',
        ]]])))]);

        $this->actingAs($analyst)->get('/signals')
            ->assertInertia(fn ($page) => $page->where('extractionMode', 'openrouter')->where('extractionLabel', 'OpenRouter')->where('extractionModel', 'vendor/model-x'));

        $response = $this->actingAs($analyst)->postJson('/signals/ingest', ['title' => 'EV Policy Brief', 'content' => $document])
            ->assertStatus(202)
            ->assertJsonPath('source.extraction.mode', 'openrouter')
            ->assertJsonPath('source.extraction.label', 'OpenRouter');

        $this->assertStringContainsString('oleh OpenRouter (vendor/model-x-2026)', $response->json('source.message'));
        $signal = Signal::first();
        $this->assertSame('openrouter', $signal->extraction_provider);
        $this->assertSame('vendor/model-x-2026', $signal->extraction_model);
        $this->assertTrue($signal->quote_verified);
    }
}
