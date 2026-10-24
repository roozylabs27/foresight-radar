<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Any provider that speaks the OpenAI chat completions API
 * (`POST {base_url}/chat/completions`): OpenRouter, OpenCode Zen, Ollama and
 * LM Studio, Groq, DeepSeek, Gemini's OpenAI endpoint, and others.
 *
 * - `structured_output = json_schema` sends the schema as `response_format`
 *   (strict); `json_object` asks for a JSON object and puts the schema in the
 *   user message, for providers without schema support.
 * - Retries cover 429, 5xx and connection errors; the timeout is per attempt.
 * - Logs carry purpose, provider, model, finish reason, token usage and
 *   duration only: never prompt text, document text or the key.
 */
class OpenAiCompatibleClient implements StructuredLlmClient
{
    public function __construct(private readonly array $config) {}

    public function isAvailable(): bool
    {
        return trim((string) ($this->config['base_url'] ?? '')) !== ''
            && trim((string) ($this->config['model'] ?? '')) !== ''
            && (trim((string) ($this->config['api_key'] ?? '')) !== '' || ! empty($this->config['allow_no_key']));
    }

    public function provider(): string
    {
        return Str::slug((string) ($this->config['name'] ?? 'openai-compatible')) ?: 'openai-compatible';
    }

    public function label(): string
    {
        return (string) ($this->config['label'] ?? '') ?: Str::headline($this->provider());
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? '');
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
        $jsonSchema = ($this->config['structured_output'] ?? 'json_schema') !== 'json_object';
        $user = $jsonSchema
            ? $request->user
            : $request->user."\n\nRespond with one JSON object that matches this JSON Schema, and nothing else:\n"
                .json_encode($request->schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $body = [
            'model' => $this->model(),
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ['role' => 'user', 'content' => $user],
            ],
            'max_tokens' => $request->maxTokens,
            'response_format' => $jsonSchema
                ? ['type' => 'json_schema', 'json_schema' => ['name' => Str::snake($request->purpose), 'strict' => true, 'schema' => $request->schema]]
                : ['type' => 'json_object'],
        ];

        // Only sent when configured: some providers reject unknown parameters.
        $reasoning = trim((string) ($this->config['reasoning_effort'] ?? ''));
        if ($reasoning !== '') {
            $body['reasoning_effort'] = $reasoning;
        }

        try {
            $response = $this->http()->post('/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw $this->fail($request, str_contains(strtolower($e->getMessage()), 'timed out') ? 'timeout' : 'api_error', null, $start, $e);
        }

        if ($response->failed()) {
            $reason = match (true) {
                $response->status() === 429 => 'rate_limited',
                in_array($response->status(), [502, 503, 529], true) => 'overloaded',
                $response->status() === 408 || $response->status() === 504 => 'timeout',
                default => 'api_error',
            };
            throw $this->fail($request, $reason, $response->status(), $start);
        }

        return $this->toResponse($response->json() ?? [], $request->purpose, (int) round((microtime(true) - $start) * 1000));
    }

    /**
     * Turn a chat completion into a response, checking why generation
     * stopped before reading content. Public so decoding can be tested.
     *
     * @throws LlmException
     */
    public function toResponse(array $completion, string $purpose, int $durationMs): LlmResponse
    {
        $choice = $completion['choices'][0] ?? [];
        $finish = $choice['finish_reason'] ?? null;
        $usage = [
            'input_tokens' => $completion['usage']['prompt_tokens'] ?? null,
            'output_tokens' => $completion['usage']['completion_tokens'] ?? null,
            'cache_read_input_tokens' => $completion['usage']['prompt_tokens_details']['cached_tokens'] ?? null,
        ];
        $model = (string) ($completion['model'] ?? $this->model());

        Log::info('llm.call', [
            'purpose' => $purpose,
            'provider' => $this->provider(),
            'model' => $model,
            'stop_reason' => $finish,
            'usage' => $usage,
            'duration_ms' => $durationMs,
        ]);

        if (! empty($choice['message']['refusal']) || $finish === 'content_filter') {
            throw new LlmException('refusal');
        }
        if ($finish === 'length') {
            throw new LlmException('max_tokens');
        }

        $data = json_decode($this->stripFences((string) ($choice['message']['content'] ?? '')), true);
        if (! is_array($data)) {
            throw new LlmException('invalid_json');
        }

        return new LlmResponse($data, $this->provider(), $model, $finish, $usage, $durationMs);
    }

    /** Some models wrap JSON in a ```json fence even when asked not to. */
    private function stripFences(string $text): string
    {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $match)) {
            return $match[1];
        }

        return $text;
    }

    private function http()
    {
        $headers = array_filter([
            // OpenRouter uses these to attribute traffic; other providers ignore them.
            'HTTP-Referer' => $this->config['referer'] ?? null,
            'X-Title' => $this->config['app_title'] ?? null,
        ]);

        $request = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            ->connectTimeout(10)
            ->retry(
                max(0, (int) ($this->config['max_retries'] ?? 1)) + 1,
                (int) ($this->config['retry_delay_ms'] ?? 1000),
                fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->status() === 429 || $exception->response->serverError())),
                throw: false,
            );

        $key = trim((string) ($this->config['api_key'] ?? ''));

        return $key === '' ? $request : $request->withToken($key);
    }

    private function fail(LlmRequest $request, string $reason, ?int $status, float $start, ?\Throwable $previous = null): LlmException
    {
        Log::warning('llm.error', [
            'purpose' => $request->purpose,
            'provider' => $this->provider(),
            'model' => $this->model(),
            'reason' => $reason,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);

        return new LlmException($reason, $previous ? $previous::class : "HTTP {$status}", $previous);
    }
}
