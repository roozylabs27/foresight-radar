<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Claude, used for signal extraction and scenario narratives. With no key
    // the app runs without AI (NullLlmClient). See docs/product/LLM_INTEGRATION.md.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
        'narrative_effort' => env('ANTHROPIC_NARRATIVE_EFFORT', 'medium'),
        // Seconds per attempt. With one retry a call takes at most ~240 s,
        // under the queued job's limit (queue.llm_job_timeout).
        'timeout' => (float) env('ANTHROPIC_TIMEOUT', 120),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 1),
        // Server-side fallback when the model declines on safety grounds:
        // "default" lets the API pick the fallback model; empty disables it.
        'fallbacks' => env('ANTHROPIC_FALLBACKS', 'default'),
    ],

    // Which LLM the app uses: "anthropic" (Claude, above) or "openai_compatible"
    // (any /chat/completions API: OpenRouter, OpenCode Zen, Ollama, Groq, ...).
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'anthropic'),
    ],

    'openai_compatible' => [
        // Short id stored with generated data, and its display name.
        'name' => env('OPENAI_COMPATIBLE_NAME', 'openrouter'),
        'label' => env('OPENAI_COMPATIBLE_LABEL', 'OpenRouter'),
        'base_url' => env('OPENAI_COMPATIBLE_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('OPENAI_COMPATIBLE_API_KEY'),
        // Required: the provider's model id, e.g. "openai/gpt-4o-mini" on OpenRouter.
        'model' => env('OPENAI_COMPATIBLE_MODEL'),
        // "json_schema" (strict schema) or "json_object" for providers without schema support.
        'structured_output' => env('OPENAI_COMPATIBLE_STRUCTURED_OUTPUT', 'json_schema'),
        // Local servers such as Ollama need no key.
        'allow_no_key' => (bool) env('OPENAI_COMPATIBLE_ALLOW_NO_KEY', false),
        // Sent as `reasoning_effort` when set ("low", "medium", "high"); empty sends nothing.
        // Reasoning models such as Gemini 3 can otherwise think past the timeout.
        'reasoning_effort' => env('OPENAI_COMPATIBLE_REASONING_EFFORT'),
        'timeout' => (int) env('OPENAI_COMPATIBLE_TIMEOUT', 120),
        'max_retries' => (int) env('OPENAI_COMPATIBLE_MAX_RETRIES', 1),
        'retry_delay_ms' => (int) env('OPENAI_COMPATIBLE_RETRY_DELAY_MS', 1000),
        'referer' => env('OPENAI_COMPATIBLE_REFERER', env('APP_URL')),
        'app_title' => env('OPENAI_COMPATIBLE_APP_TITLE', 'Foresight Radar'),
    ],

    'signal_extraction' => [
        // "llm" uses the configured LLM_PROVIDER when it is set up; "heuristic"
        // never does. ("claude", the earlier value, still means "llm".)
        'driver' => env('SIGNAL_EXTRACTION_DRIVER', 'llm'),
        'max_chars' => (int) env('SIGNAL_EXTRACTION_MAX_CHARS', 60000),
        'max_candidates' => (int) env('SIGNAL_EXTRACTION_MAX_CANDIDATES', 8),
    ],

];
