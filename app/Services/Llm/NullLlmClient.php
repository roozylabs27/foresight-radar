<?php

namespace App\Services\Llm;

/**
 * Bound when no API key is configured: never available, never calls out.
 */
class NullLlmClient implements StructuredLlmClient
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function provider(): string
    {
        return 'none';
    }

    public function label(): string
    {
        return 'Aturan';
    }

    public function model(): string
    {
        return '';
    }

    public function generateJson(LlmRequest $request): LlmResponse
    {
        throw new LlmException('unavailable', 'No LLM provider is configured.');
    }
}
