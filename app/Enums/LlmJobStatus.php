<?php

namespace App\Enums;

/**
 * State of a queued LLM job, stored on the row it works for
 * (sources.extraction_status, scenarios.generation_status).
 */
enum LlmJobStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function isPending(): bool
    {
        return $this === self::QUEUED || $this === self::PROCESSING;
    }
}
