<?php

namespace App\Services\Llm;

use App\Models\User;
use App\Models\UserLlmSetting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Throwable;

class UserLlmSettingService
{
    public function __construct(
        protected LlmClientResolver $resolver,
    ) {}

    /**
     * Supported provider specifications with curated latest models.
     */
    public const PROVIDERS = [
        'anthropic' => [
            'provider' => 'anthropic',
            'label' => 'Claude (Anthropic)',
            'description' => 'Claude models from Anthropic with advanced reasoning and analysis.',
            'default_model' => 'claude-sonnet-5-5',
            'models' => [
                'claude-sonnet-5-5',
                'claude-opus-5-5',
                'claude-3-7-sonnet-latest',
                'claude-3-7-sonnet-20250219',
                'claude-3-5-sonnet-latest',
                'claude-3-5-haiku-latest',
            ],
            'needs_base_url' => false,
            'default_base_url' => null,
            'key_placeholder' => 'sk-ant-api03-...',
            'doc_url' => 'https://console.anthropic.com/settings/keys',
        ],
        'openai' => [
            'provider' => 'openai',
            'label' => 'ChatGPT (OpenAI)',
            'description' => 'GPT-4o and reasoning models directly via the OpenAI API.',
            'default_model' => 'gpt-4o',
            'models' => [
                'gpt-4o',
                'gpt-4o-mini',
                'o3-mini',
                'o1',
                'gpt-4.5-preview',
                'chatgpt-4o-latest',
            ],
            'needs_base_url' => false,
            'default_base_url' => 'https://api.openai.com/v1',
            'key_placeholder' => 'sk-proj-...',
            'doc_url' => 'https://platform.openai.com/api-keys',
        ],
        'gemini' => [
            'provider' => 'gemini',
            'label' => 'Google Gemini',
            'description' => 'Google Gemini 3.8 Flash and 2.5 series models via Google AI Studio API.',
            'default_model' => 'gemini-3.8-flash',
            'models' => [
                'gemini-3.8-flash',
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-1.5-pro',
            ],
            'needs_base_url' => false,
            'default_base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai/',
            'key_placeholder' => 'AIzaSy...',
            'doc_url' => 'https://aistudio.google.com/app/apikey',
        ],
        'openai_compatible' => [
            'provider' => 'openai_compatible',
            'label' => 'OpenRouter / Custom Gateway',
            'description' => 'Any OpenAI-compatible completions endpoint (OpenRouter, Groq, Ollama, vLLM).',
            'default_model' => 'openrouter/auto',
            'models' => [
                'openrouter/auto',
                'deepseek/deepseek-r1',
                'anthropic/claude-3.7-sonnet',
                'meta-llama/llama-3.3-70b-instruct',
            ],
            'needs_base_url' => true,
            'default_base_url' => 'https://openrouter.ai/api/v1',
            'key_placeholder' => 'sk-or-v1-...',
            'doc_url' => 'https://openrouter.ai/keys',
        ],
    ];

    /**
     * Get the full catalog of providers with current user settings merged.
     */
    public function getProvidersCatalog(User|Authenticatable|null $user): array
    {
        $userSettings = collect();
        if ($user instanceof User) {
            $userSettings = $user->llmSettings()
                ->get()
                ->keyBy('provider');
        } elseif ($user !== null) {
            $userSettings = UserLlmSetting::where('user_id', $user->getAuthIdentifier())
                ->get()
                ->keyBy('provider');
        }

        $catalog = [];
        foreach (self::PROVIDERS as $key => $spec) {
            /** @var UserLlmSetting|null $setting */
            $setting = $userSettings->get($key);

            $catalog[] = [
                ...$spec,
                'is_configured' => $setting !== null && ! empty($setting->api_key),
                'is_active' => $setting !== null && (bool) $setting->is_active,
                'configured_model' => $setting?->model ?? $spec['default_model'],
                'configured_base_url' => $setting?->base_url ?? $spec['default_base_url'],
                'masked_api_key' => $setting?->masked_api_key,
                'updated_at' => $setting?->updated_at?->toISOString(),
            ];
        }

        return $catalog;
    }

    /**
     * Save or update a user's LLM setting for a specific provider.
     */
    public function saveProvider(User|Authenticatable $user, array $data): UserLlmSetting
    {
        $userId = $user instanceof User ? $user->id : (int) $user->getAuthIdentifier();
        $provider = $data['provider'];
        $spec = self::PROVIDERS[$provider] ?? null;

        $setting = UserLlmSetting::firstOrNew([
            'user_id' => $userId,
            'provider' => $provider,
        ]);

        $setting->label = $spec['label'] ?? ucfirst($provider);
        $setting->model = $data['model'] ?? ($spec['default_model'] ?? '');
        $setting->base_url = ! empty($data['base_url']) ? $data['base_url'] : ($spec['default_base_url'] ?? null);

        // Update API key only if provided (allows keeping existing key unchanged)
        if (array_key_exists('api_key', $data) && ! empty($data['api_key'])) {
            $setting->api_key = $data['api_key'];
        }

        if (isset($data['settings']) && is_array($data['settings'])) {
            $setting->settings = $data['settings'];
        }

        $setting->save();

        return $setting;
    }

    /**
     * Activate exactly one provider for the user, atomically deactivating any others.
     */
    public function activateProvider(User|Authenticatable $user, string $provider): UserLlmSetting
    {
        $userId = $user instanceof User ? $user->id : (int) $user->getAuthIdentifier();

        return DB::transaction(function () use ($userId, $provider) {
            // Deactivate all providers for this user
            UserLlmSetting::where('user_id', $userId)->update(['is_active' => false]);

            // Find or initialize the setting for the selected provider
            $setting = UserLlmSetting::where('user_id', $userId)
                ->where('provider', $provider)
                ->firstOrFail();

            if (empty($setting->api_key)) {
                throw new \InvalidArgumentException("Cannot activate provider {$provider} without a configured API key.");
            }

            $setting->is_active = true;
            $setting->save();

            return $setting;
        });
    }

    /**
     * Deactivate a provider (or all providers) for a user.
     */
    public function deactivateProvider(User|Authenticatable $user, ?string $provider = null): void
    {
        $userId = $user instanceof User ? $user->id : (int) $user->getAuthIdentifier();
        $query = UserLlmSetting::where('user_id', $userId);
        if ($provider) {
            $query->where('provider', $provider);
        }
        $query->update(['is_active' => false]);
    }

    /**
     * Delete/reset a user's LLM configuration for a provider.
     */
    public function deleteProvider(User|Authenticatable $user, string $provider): void
    {
        $userId = $user instanceof User ? $user->id : (int) $user->getAuthIdentifier();
        UserLlmSetting::where('user_id', $userId)
            ->where('provider', $provider)
            ->delete();
    }

    /**
     * Test connection against the provider API using candidate or stored credentials.
     */
    public function testConnection(array $attributes): array
    {
        $startTime = microtime(true);

        try {
            $client = $this->resolver->createClient($attributes);

            if (! $client->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Client is not available. Please ensure the API key is provided.',
                ];
            }

            $request = new LlmRequest(
                purpose: 'connection_test',
                system: 'You are an API connection tester. Reply strictly with the requested JSON.',
                user: 'Reply with status ok',
                schema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string'],
                    ],
                    'required' => ['status'],
                    'additionalProperties' => false,
                ],
                maxTokens: 2048,
            );

            $response = $client->generateJson($request);
            $latencyMs = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'latency_ms' => $latencyMs,
                'model' => $attributes['model'] ?? '',
                'provider' => $attributes['provider'] ?? '',
                'message' => 'Connection verified successfully.',
                'raw' => $response->data,
            ];
        } catch (Throwable $th) {
            $latencyMs = round((microtime(true) - $startTime) * 1000);
            $message = $th instanceof LlmException
                ? $th->userMessage()
                : $th->getMessage();

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => 'Uji koneksi gagal: '.$message,
            ];
        }
    }
}
