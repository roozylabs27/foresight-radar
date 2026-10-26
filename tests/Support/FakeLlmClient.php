<?php

namespace Tests\Support;

use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\LlmResponse;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Offline stand-in for the LLM: answers from a queue and records each request
 * with the DB transaction level at the moment it was made.
 */
class FakeLlmClient implements StructuredLlmClient
{
    /** @var array<int, array|LlmException> */
    private array $queue = [];

    /** @var array<int, array{request: LlmRequest, transaction_level: int}> */
    public array $calls = [];

    public function __construct(private bool $available = true) {}

    /**
     * Queue the next answer: a decoded JSON array, or an exception to throw.
     */
    public function respond(array|LlmException $answer): static
    {
        $this->queue[] = $answer;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available;
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
        return 'claude-fake';
    }

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return $this->generateJson($request);
    }

    public function generateJson(LlmRequest $request): LlmResponse
    {
        $this->calls[] = ['request' => $request, 'transaction_level' => DB::transactionLevel()];

        if ($this->queue === []) {
            throw new RuntimeException("FakeLlmClient has no answer queued for {$request->purpose}.");
        }

        $answer = array_shift($this->queue);
        if ($answer instanceof LlmException) {
            throw $answer;
        }

        return new LlmResponse($answer, $this->provider(), $this->model(), 'end_turn', ['input_tokens' => 0, 'output_tokens' => 0]);
    }

    public function lastRequest(): ?LlmRequest
    {
        return $this->calls === [] ? null : end($this->calls)['request'];
    }
}
