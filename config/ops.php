<?php

return [
    // PRD-04: where the hourly failed-jobs check mails its summary; empty means log only.
    'alert_email' => env('OPS_ALERT_EMAIL'),

    // Requests per minute per signed-in user (RouteServiceProvider limiters `app` and `visualization`).
    // Production keeps the defaults; only the isolated browser E2E stack raises them, because its tests
    // click far faster than a person (found 2026-11-16: CI runs hit 429 mid-journey).
    'rate_limits' => [
        'app' => (int) env('RATE_LIMIT_APP_PER_MINUTE', 120),
        'visualization' => (int) env('RATE_LIMIT_VISUALIZATION_PER_MINUTE', 60),
    ],
];
