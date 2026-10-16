<?php

namespace App\Services\Llm;

/**
 * A language model that answers with JSON matching a schema. Features depend
 * on this interface only, so the provider, or a queue in front of it, can
 * change without touching them.
 */
interface StructuredLlmClient
{
    /** False when no provider is configured; callers then use their non-AI path. */
    public function isAvailable(): bool;

    public function provider(): string;

    public function model(): string;

    /**
     * @throws LlmException
     */
    public function generateJson(LlmRequest $request): LlmResponse;
}
