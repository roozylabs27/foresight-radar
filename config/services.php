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
        // under the 300 s PHP and nginx limits.
        'timeout' => (float) env('ANTHROPIC_TIMEOUT', 120),
        'max_retries' => (int) env('ANTHROPIC_MAX_RETRIES', 1),
        // Server-side fallback when the model declines on safety grounds:
        // "default" lets the API pick the fallback model; empty disables it.
        'fallbacks' => env('ANTHROPIC_FALLBACKS', 'default'),
    ],

    'signal_extraction' => [
        // "claude" uses the LLM when a key is set; "heuristic" never does.
        'driver' => env('SIGNAL_EXTRACTION_DRIVER', 'claude'),
        'max_chars' => (int) env('SIGNAL_EXTRACTION_MAX_CHARS', 60000),
        'max_candidates' => (int) env('SIGNAL_EXTRACTION_MAX_CANDIDATES', 8),
    ],

];
