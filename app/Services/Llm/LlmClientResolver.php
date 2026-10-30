<?php

namespace App\Services\Llm;

use App\Models\TenantLlmSetting;
use App\Models\User;
use App\Models\UserLlmSetting;
use App\Services\TenantContext;

class LlmClientResolver
{
    /**
     * The client for a user, in this order (TENANT-008): the user's own active key, unless their
     * organization has an AI configuration that does not allow personal keys; then the
     * organization's key; then the platform default. Every client counts against the tenant's
     * monthly token quota (QuotaLimitedLlmClient).
     */
    public function resolveForUser(?User $user = null, bool $fallbackToDefault = true): ?StructuredLlmClient
    {
        $tenantSetting = $this->tenantSetting($user);

        if ($user && ($tenantSetting === null || $tenantSetting->allow_user_byok)) {
            $activeSetting = $user->llmSettings()
                ->where('is_active', true)
                ->first();

            if ($activeSetting && ! empty($activeSetting->api_key)) {
                $client = $this->createClientFromSetting($activeSetting);
                if ($client->isAvailable()) {
                    return QuotaLimitedLlmClient::wrap($client);
                }
            }
        }

        if ($tenantSetting && $tenantSetting->is_enabled && ! empty($tenantSetting->api_key)) {
            $client = $this->createClientFromTenantSetting($tenantSetting);
            if ($client->isAvailable()) {
                return QuotaLimitedLlmClient::wrap($client);
            }
        }

        // The platform default is registered already wrapped (AppServiceProvider).
        return $fallbackToDefault ? app(StructuredLlmClient::class) : null;
    }

    /**
     * Create a concrete StructuredLlmClient from the organization's AI configuration.
     */
    public function createClientFromTenantSetting(TenantLlmSetting $setting): StructuredLlmClient
    {
        return $this->createClient([
            'provider' => $setting->provider,
            'label' => null,
            'api_key' => $setting->api_key,
            'model' => (string) $setting->model,
            'base_url' => $setting->base_url,
            'settings' => [],
        ]);
    }

    /**
     * The AI configuration of the tenant in context (a queued job runs as its tenant),
     * otherwise of the user's own tenant.
     */
    private function tenantSetting(?User $user): ?TenantLlmSetting
    {
        $tenantId = app(TenantContext::class)->getTenantId() ?? $user?->tenant_id;

        return $tenantId ? TenantLlmSetting::where('tenant_id', $tenantId)->first() : null;
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
