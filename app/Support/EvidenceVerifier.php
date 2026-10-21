<?php

namespace App\Support;

/**
 * Checks that a quote appears verbatim in its source text. Whitespace runs
 * (including line breaks and non-breaking spaces) are treated as one space;
 * everything else, case included, must match exactly.
 */
class EvidenceVerifier
{
    public const MIN_LENGTH = 20;

    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{2007}\x{202F}]+/u', ' ', $text));
    }

    public static function verify(?string $quote, ?string $source): bool
    {
        $needle = self::normalize((string) $quote);
        if (mb_strlen($needle) < self::MIN_LENGTH || $source === null) {
            return false;
        }

        return str_contains(self::normalize($source), $needle);
    }
}
