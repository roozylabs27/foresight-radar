<?php

namespace App\Services\Llm;

use App\Models\User;
use App\Models\UserLlmSetting;

class LlmClientResolver
{
    /**
     * Resolve the active StructuredLlmClient for a given user, or fallback to the system client.
     */
    public function resolveForUser(?User $user = null, bool $fallbackToDefault = true): ?StructuredLlmClient
    {
        if ($user) {
            $activeSetting = $user->llmSettings()
                ->where('is_active', true)
                ->first();

            if ($activeSetting && ! empty($activeSetting->api_key)) {
                $client = $this->createClientFromSetting($activeSetting);
                if ($client->isAvailable()) {
                    return $client;
                }
            }
        }

        // Fallback to the application default client if requested
        return $fallbackToDefault ? app(StructuredLlmClient::class) : null;
    }

    /**
     * Resolve the active StructuredLlmClient for a user ID.
     */
    public function resolveForUserId(?int $userId = null, bool $fallbackToDefault = true): ?StructuredLlmClient
    {
        if (! $userId) {
            return $fallbackToDefault ? app(StructuredLlmClient::class) : null;
        }

        $user = User::find($userId);

        return $this->resolveForUser($user, $fallbackToDefault);
    }

    /**
     * Create a concrete StructuredLlmClient from a UserLlmSetting instance.
     */
    public function createClientFromSetting(UserLlmSetting $setting): StructuredLlmClient
    {
        return $this->createClient([
            'provider' => $setting->provider,
            'label' => $setting->label,
            'api_key' => $setting->api_key,
            'model' => $setting->model,
            'base_url' => $setting->base_url,
            'settings' => $setting->settings ?? [],
        ]);
    }

    /**
     * Create a concrete StructuredLlmClient from attribute array.
     */
    public function createClient(array $attributes): StructuredLlmClient
    {
        $provider = $attributes['provider'] ?? 'anthropic';
        $apiKey = $attributes['api_key'] ?? null;
        $model = $attributes['model'] ?? '';
        $settings = $attributes['settings'] ?? [];

        return match ($provider) {
            'anthropic' => new AnthropicStructuredClient([
                'api_key' => $apiKey,
                'model' => $model,
                'timeout' => $settings['timeout'] ?? (float) config('services.anthropic.timeout', 240),
                'max_retries' => $settings['max_retries'] ?? 1,
            ]),
            'openai' => new OpenAiCompatibleClient([
                'name' => 'openai',
                'label' => 'OpenAI',
                'base_url' => $attributes['base_url'] ?: 'https://api.openai.com/v1',
                'api_key' => $apiKey,
                'model' => $model,
                'structured_output' => 'json_schema',
                'timeout' => $settings['timeout'] ?? (int) config('services.openai_compatible.timeout', 240),
                'max_retries' => $settings['max_retries'] ?? 1,
            ]),
            'gemini' => new OpenAiCompatibleClient([
                'name' => 'gemini',
                'label' => 'Google Gemini',
                'base_url' => $attributes['base_url'] ?: 'https://generativelanguage.googleapis.com/v1beta/openai/',
                'api_key' => $apiKey ?: config('services.openai_compatible.api_key'),
                'model' => $model,
                'structured_output' => 'json_object',
                'reasoning_effort' => $settings['reasoning_effort'] ?? 'low',
                'timeout' => $settings['timeout'] ?? (int) config('services.openai_compatible.timeout', 240),
                'max_retries' => $settings['max_retries'] ?? 0,
            ]),
            default => new OpenAiCompatibleClient([
                'name' => 'openai_compatible',
                'label' => $attributes['label'] ?? 'OpenAI-Compatible',
                'base_url' => $attributes['base_url'] ?? '',
                'api_key' => $apiKey,
                'model' => $model,
                'structured_output' => $settings['structured_output'] ?? 'json_schema',
                'timeout' => $settings['timeout'] ?? (int) config('services.openai_compatible.timeout', 240),
                'max_retries' => $settings['max_retries'] ?? 1,
            ]),
        };
    }
}
