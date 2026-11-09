<?php

namespace App\Support;

/**
 * Word overlap between short texts (roadmap R2.5, R2.6): distinct lowercased words, split on
 * anything that is not a letter or digit, without 1-letter tokens and common Indonesian / English
 * stop words, compared with the Jaccard index.
 */
final class TextSimilarity
{
    /** Overlap from which two items in different quadrants count as alike. */
    public const THRESHOLD = 0.6;

    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'akan', 'ini', 'itu', 'oleh', 'atau', 'juga', 'lebih', 'tidak', 'masih', 'sudah', 'tetap',
        'the', 'of', 'and', 'to', 'in', 'a', 'an', 'for', 'is', 'are', 'on', 'by', 'with',
    ];

    /** @return array<int, string> */
    public static function words(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn ($token) => mb_strlen($token) >= 2 && ! in_array($token, self::STOPWORDS, true))));
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    public static function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / count(array_unique([...$a, ...$b]));
    }
}
