<?php

namespace App\Services\Llm;

use App\Models\AiGenerationRun;
use Illuminate\Support\Str;

/**
 * Writes one `ai_generation_runs` row per AI call (roadmap R1.12). Call sites record the outcome
 * they observed: the response of a completed call, or the reason it failed or was rejected.
 */
class AiGenerationRecorder
{
    /**
     * @param  array<string, mixed>  $inputRefs  what the call worked on, by public id
     */
    public function completed(string $purpose, LlmResponse $response, string $promptVersion, array $inputRefs, ?int $userId = null, ?int $projectId = null): AiGenerationRun
    {
        return AiGenerationRun::create([
            'purpose' => $purpose,
            'provider' => $response->provider,
            'model' => $response->model,
            'prompt_version' => $promptVersion,
            'input_refs' => $inputRefs,
            'status' => AiGenerationRun::COMPLETED,
            'usage' => $response->usage,
            'duration_ms' => $response->durationMs ?: null,
            'created_by' => $userId,
            'project_id' => $projectId,
        ]);
    }

    /**
     * A call that did not produce usable output: the provider failed (`failed`) or its answer was
     * refused by our checks (`rejected`).
     *
     * @param  array<string, mixed>  $inputRefs
     */
    public function unsuccessful(string $status, string $purpose, StructuredLlmClient $llm, string $promptVersion, array $inputRefs, string $error, ?int $userId = null, ?int $projectId = null, ?LlmResponse $response = null): AiGenerationRun
    {
        return AiGenerationRun::create([
            'purpose' => $purpose,
            'provider' => $response?->provider ?? $llm->provider(),
            'model' => $response?->model ?? $llm->model(),
            'prompt_version' => $promptVersion,
            'input_refs' => $inputRefs,
            'status' => $status,
            'error' => Str::limit($error, 500, ''),
            'usage' => $response?->usage,
            'duration_ms' => $response?->durationMs ?: null,
            'created_by' => $userId,
            'project_id' => $projectId,
        ]);
    }
}
