<?php

namespace App\Services\Llm;

/**
 * One structured-output call. `system` must be constant per prompt version so
 * it can be cached; everything that varies (documents, evidence) goes in
 * `user`, wrapped in tags by the prompt class.
 */
final class LlmRequest
{
    /**
     * @param  string  $purpose  short label for logs, e.g. "signal_extraction"
     * @param  array<string, mixed>  $schema  JSON Schema of the expected answer
     * @param  string|null  $effort  overrides the configured effort
     */
    public function __construct(
        public readonly string $purpose,
        public readonly string $system,
        public readonly string $user,
        public readonly array $schema,
        public readonly int $maxTokens = 16000,
        public readonly ?string $effort = null,
    ) {
    }
}
