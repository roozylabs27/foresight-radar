<?php

namespace App\Services\Llm;

/**
 * Deterministic AI for the isolated browser E2E stack only (APP_ENV=e2e and LLM_PROVIDER=e2e; see
 * AppServiceProvider). It is never bound in any other environment. It answers the focal-issue assist
 * (step 1) and lets a test steer it through markers in the focal question:
 *   [e2e-slow]  wait a few seconds first, so the pending state is visible;
 *   [e2e-fail]  fail like an overloaded provider, so the error and retry path is visible.
 * Every other purpose reports the provider as overloaded: those assists are covered by PHPUnit with
 * FakeLlmClient. This is a test double: it proves the app's handling, not a real provider.
 */
class E2eScriptedLlmClient implements StructuredLlmClient
{
    public function isAvailable(): bool
    {
        return true;
    }

    public function provider(): string
    {
        return 'e2e';
    }

    public function label(): string
    {
        return 'E2E scripted';
    }

    public function model(): string
    {
        return 'e2e-scripted-1';
    }

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        return $this->generateJson($request);
    }

    public function generateJson(LlmRequest $request): LlmResponse
    {
        if (str_contains($request->user, '[e2e-fail]')) {
            throw new LlmException('overloaded', 'E2E scripted failure');
        }
        if (str_contains($request->user, '[e2e-slow]')) {
            sleep(4);
        }
        if ($request->purpose !== 'scenario_focal_issue') {
            throw new LlmException('overloaded', "E2E scripted client has no answer for {$request->purpose}");
        }

        return new LlmResponse(
            data: [
                'critique' => [
                    ['issue' => 'unclear_scope', 'comment' => 'Cakupan industri belum disebut dengan jelas.'],
                ],
                'alternatives' => [
                    ['question' => 'Bagaimana adopsi kendaraan listrik di Indonesia berkembang hingga 2035, dan apa artinya bagi rantai pasok baterai?', 'rationale' => 'Menyebut horizon dan cakupan industri.'],
                    ['question' => 'Faktor apa yang paling menentukan pangsa kendaraan listrik di pasar otomotif Indonesia pada 2035?', 'rationale' => 'Terbuka dan berfokus pada pendorong.'],
                ],
            ],
            provider: $this->provider(),
            model: $this->model(),
            stopReason: 'stop',
        );
    }
}
