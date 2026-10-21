<?php

namespace Tests\Feature\Llm;

use App\Services\Llm\AnthropicStructuredClient;
use App\Services\Llm\LlmException;
use App\Services\Llm\NullLlmClient;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Decoding of SDK messages, with plain objects shaped like the SDK's Message.
 * Nothing here reaches the network.
 */
class AnthropicStructuredClientTest extends TestCase
{
    private function client(): AnthropicStructuredClient
    {
        return new AnthropicStructuredClient(['api_key' => 'sk-test-not-used', 'model' => 'claude-opus-5-5']);
    }

    private function message(string $stopReason, array $blocks, ?object $stopDetails = null): object
    {
        return (object) [
            'model' => 'claude-opus-5-5',
            'stopReason' => $stopReason,
            'stopDetails' => $stopDetails,
            'content' => array_map(fn ($block) => (object) $block, $blocks),
            'usage' => (object) ['inputTokens' => 1200, 'outputTokens' => 300, 'cacheReadInputTokens' => 900, 'cacheCreationInputTokens' => 0],
        ];
    }

    public function test_reads_json_from_the_first_text_block_after_thinking(): void
    {
        $response = $this->client()->toResponse($this->message('end_turn', [
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => '{"signals": [{"title": "Euro-5"}]}'],
        ]), 'signal_extraction', 1500);

        $this->assertSame([['title' => 'Euro-5']], $response->data['signals']);
        $this->assertSame('claude', $response->provider);
        $this->assertSame('claude-opus-5-5', $response->model);
        $this->assertSame(900, $response->usage['cache_read_input_tokens']);
        $this->assertSame(1500, $response->durationMs);
    }

    public function test_refusal_and_max_tokens_are_reported_before_content_is_read(): void
    {
        foreach (['refusal' => 'refusal', 'max_tokens' => 'max_tokens'] as $stopReason => $reason) {
            try {
                $this->client()->toResponse($this->message($stopReason, [['type' => 'text', 'text' => '{"partial":']]), 'test', 10);
                $this->fail("Expected {$reason}");
            } catch (LlmException $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertNotSame('', $e->userMessage());
            }
        }
    }

    public function test_broken_json_or_no_text_block_is_invalid(): void
    {
        foreach ([[['type' => 'text', 'text' => 'Here is the JSON: {']], [['type' => 'thinking', 'thinking' => '']]] as $blocks) {
            try {
                $this->client()->toResponse($this->message('end_turn', $blocks), 'test', 10);
                $this->fail('Expected invalid_json');
            } catch (LlmException $e) {
                $this->assertSame('invalid_json', $e->reason);
            }
        }
    }

    public function test_logs_usage_but_never_content(): void
    {
        Log::spy();

        $this->client()->toResponse($this->message('end_turn', [
            ['type' => 'text', 'text' => '{"secret": "CONFIDENTIAL DOCUMENT TEXT"}'],
        ]), 'signal_extraction', 42);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $encoded = json_encode($context);

            return $message === 'llm.call'
                && $context['purpose'] === 'signal_extraction'
                && $context['usage']['input_tokens'] === 1200
                && ! str_contains($encoded, 'CONFIDENTIAL')
                && ! str_contains($encoded, 'sk-test');
        })->once();
    }

    public function test_container_binds_null_client_without_a_key(): void
    {
        config(['services.anthropic.api_key' => '']);
        $this->app->forgetInstance(StructuredLlmClient::class);

        $client = $this->app->make(StructuredLlmClient::class);

        $this->assertInstanceOf(NullLlmClient::class, $client);
        $this->assertFalse($client->isAvailable());
        $this->expectException(LlmException::class);
        $client->generateJson(new \App\Services\Llm\LlmRequest('test', 'system', 'user', ['type' => 'object']));
    }
}
