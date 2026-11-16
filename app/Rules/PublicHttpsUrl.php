<?php

namespace App\Rules;

use App\Support\Net\OutboundGuard;
use App\Support\Net\UnsafeOutboundUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Production readiness 2026-11-13 (SEC-PR-02): an AI provider base URL a user or organisation types must
 * be a public HTTPS address on port 443 whose DNS answers are all public (OutboundGuard). This catches a
 * bad URL when it is saved; the request itself is checked again and pinned to the checked IP, so a DNS
 * answer that changes after saving (DNS rebinding) is still refused.
 */
class PublicHttpsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Alamat API harus berupa URL https publik (bukan alamat lokal atau jaringan internal).');

            return;
        }

        try {
            app(OutboundGuard::class)->target($value);
        } catch (UnsafeOutboundUrl $e) {
            $fail($e->getMessage());
        }
    }
}
