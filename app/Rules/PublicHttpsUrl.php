<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Production readiness 2026-11-13 (SEC-PR-02): an AI provider base URL a user types must be a public
 * HTTPS address, so the server cannot be pointed at itself, the Docker network or cloud metadata.
 * Rejected: non-https schemes, IP literals in private, loopback, link-local or reserved ranges,
 * `localhost`, single-label names (Docker service names) and internal suffixes.
 * Not covered: a public name whose DNS later resolves to a private address (DNS rebinding); see
 * docs/production-readiness/known-risks.md.
 */
class PublicHttpsUrl implements ValidationRule
{
    private const INTERNAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home', '.corp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = 'Alamat API harus berupa URL https publik (bukan alamat lokal atau jaringan internal).';
        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail($message);

            return;
        }

        $parts = parse_url($value);
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            $fail($message);

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                $fail($message);
            }

            return;
        }

        if ($host === 'localhost' || ! str_contains($host, '.') || str_ends_with($host, '.')) {
            $fail($message);

            return;
        }
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                $fail($message);

                return;
            }
        }
    }
}
