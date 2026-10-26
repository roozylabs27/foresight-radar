<?php

namespace App\Console\Commands;

use App\Services\Llm\LlmException;
use App\Services\Llm\LlmRequest;
use App\Services\Llm\StructuredLlmClient;
use Illuminate\Console\Command;

/**
 * One small structured call to check the key, model and SDK wiring.
 * It calls the paid API, so it asks for confirmation unless --yes is given.
 */
class LlmSmoke extends Command
{
    protected $signature = 'llm:smoke {--yes : Skip the cost confirmation}';

    protected $description = 'Send one tiny structured request to the configured LLM (costs a few tokens)';

    public function handle(StructuredLlmClient $llm): int
    {
        if (! $llm->isAvailable()) {
            $this->warn('No LLM is configured (ANTHROPIC_API_KEY is empty).');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm("This calls the paid {$llm->provider()} API ({$llm->model()}). Continue?")) {
            return self::SUCCESS;
        }

        try {
            $response = $llm->generateJson(new LlmRequest(
                purpose: 'smoke_test',
                system: 'You answer health checks for a software integration with a short JSON object.',
                user: 'Reply with ok set to true and one short sentence in note.',
                schema: [
                    'type' => 'object',
                    'properties' => ['ok' => ['type' => 'boolean'], 'note' => ['type' => 'string']],
                    'required' => ['ok', 'note'],
                    'additionalProperties' => false,
                ],
                maxTokens: 2000,
                effort: 'low',
            ));
        } catch (LlmException $e) {
            $this->error("Failed: {$e->reason} ({$e->getMessage()})");

            return self::FAILURE;
        }

        $this->info('OK: '.json_encode($response->data));
        $this->line("Model: {$response->model} | stop: {$response->stopReason} | {$response->durationMs} ms");
        $this->line('Usage: '.json_encode($response->usage));

        return self::SUCCESS;
    }
}
