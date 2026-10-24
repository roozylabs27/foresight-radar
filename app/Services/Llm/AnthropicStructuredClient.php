<?php

namespace App\Services\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\RateLimitException;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;

/**
 * Claude through the official PHP SDK, with JSON-schema structured output.
 *
 * - The system prompt is sent as a cached block; the varying text is the
 *   user message.
 * - Server-side fallback ("default" routing) is on unless disabled in config,
 *   so a safety decline on the main model is retried on another model inside
 *   the same call. The model that answered is reported back.
 * - Logs carry purpose, model, stop reason, token usage and duration only:
 *   never prompt text, document text or the key.
 */
class AnthropicStructuredClient implements StructuredLlmClient
{
    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private ?Client $client = null;

    public function __construct(private readonly array $config) {}

    public function isAvailable(): bool
    {
        return trim((string) ($this->config['api_key'] ?? '')) !== '';
    }

    public function provider(): string
    {
        return 'claude';
    }

    public function label(): string
    {
        return 'Claude';
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'claude-opus-5-5');
    }

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return $this->generateJson($request);
    }

    public function generateJson(LlmRequest $request): LlmResponse
    {
        if (! $this->isAvailable()) {
            throw new LlmException('unavailable');
        }

        $start = microtime(true);
        $fallbacks = $this->config['fallbacks'] ?? 'default';

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: $request->maxTokens,
                messages: [['role' => 'user', 'content' => $request->user]],
                model: $this->model(),
                system: [
                    ['type' => 'text', 'text' => $request->system, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                outputConfig: [
                    'effort' => $request->effort ?? ($this->config['effort'] ?? 'medium'),
                    'format' => ['type' => 'json_schema', 'schema' => $request->schema],
                ],
                fallbacks: $fallbacks ?: null,
                betas: $fallbacks ? [self::FALLBACK_BETA] : null,
            );
        } catch (APITimeoutException $e) {
            throw $this->fail($request, 'timeout', $e, $start);
        } catch (RateLimitException $e) {
            throw $this->fail($request, 'rate_limited', $e, $start);
        } catch (APIStatusException $e) {
            $reason = match ($e->type?->value) {
                'overloaded_error' => 'overloaded',
                'rate_limit_error' => 'rate_limited',
                default => 'api_error',
            };
            throw $this->fail($request, $reason, $e, $start);
        } catch (APIConnectionException $e) {
            throw $this->fail($request, 'api_error', $e, $start);
        }

        return $this->toResponse($message, $request->purpose, (int) round((microtime(true) - $start) * 1000));
    }

    /**
     * Turn an SDK message into a response, checking the stop reason before
     * reading content. Public so the decoding can be tested without a network.
     *
     * @throws LlmException
     */
    public function toResponse(object $message, string $purpose, int $durationMs): LlmResponse
    {
        $stopReason = $message->stopReason ?? null;
        $usage = [
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
            'cache_read_input_tokens' => $message->usage->cacheReadInputTokens ?? null,
            'cache_creation_input_tokens' => $message->usage->cacheCreationInputTokens ?? null,
        ];
        $model = (string) ($message->model ?? $this->model());

        Log::info('llm.call', [
            'purpose' => $purpose,
            'model' => $model,
            'stop_reason' => $stopReason,
            'usage' => $usage,
            'duration_ms' => $durationMs,
        ]);

        if ($stopReason === 'refusal') {
            throw new LlmException('refusal', 'Declined: '.($message->stopDetails->category ?? 'unspecified'));
        }
        if ($stopReason === 'max_tokens') {
            throw new LlmException('max_tokens');
        }

        $text = null;
        foreach ($message->content ?? [] as $block) {
            if (($block->type ?? null) === 'text') {
                $text = (string) $block->text;
                break;
            }
        }

        $data = $text === null ? null : json_decode($text, true);
        if (! is_array($data)) {
            throw new LlmException('invalid_json');
        }

        return new LlmResponse($data, $this->provider(), $model, $stopReason, $usage, $durationMs);
    }

    private function fail(LlmRequest $request, string $reason, \Throwable $e, float $start): LlmException
    {
        Log::warning('llm.error', [
            'purpose' => $request->purpose,
            'model' => $this->model(),
            'reason' => $reason,
            'status' => $e instanceof APIStatusException ? $e->status : null,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);

        return new LlmException($reason, $e::class, $e);
    }

    private function client(): Client
    {
        // The SDK leaves timeouts to the HTTP transport, so the limit is set on Guzzle.
        return $this->client ??= new Client(
            apiKey: (string) $this->config['api_key'],
            requestOptions: [
                'maxRetries' => (int) ($this->config['max_retries'] ?? 1),
                'transporter' => new GuzzleClient([
                    'timeout' => (float) ($this->config['timeout'] ?? 240),
                    'connect_timeout' => 10,
                ]),
            ],
        );
    }
}
