<?php

namespace App\Services\Llm;

final class LlmResponse
{
    /**
     * @param  array<string, mixed>  $data  the decoded JSON answer
     * @param  array<string, int|null>  $usage  token counts reported by the provider
     */
    public function __construct(
        public readonly array $data,
        public readonly string $provider,
        public readonly string $model,
        public readonly ?string $stopReason = null,
        public readonly array $usage = [],
        public readonly int $durationMs = 0,
    ) {
    }
}
