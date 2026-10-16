<?php

namespace App\Services\Llm\Prompts;

/**
 * Helpers for putting untrusted text into a prompt.
 */
final class PromptText
{
    /** Tags the prompts use to mark data blocks. */
    public const DATA_TAGS = ['source_title', 'source_document', 'driving_forces', 'evidence', 'scenario'];

    /**
     * Wrap text in a tag. Closing tags of any data block inside the text are
     * neutralised, so document text cannot end its block and pose as
     * instructions.
     */
    public static function tag(string $name, string $text): string
    {
        $names = implode('|', array_map(fn ($tag) => preg_quote($tag, '#'), array_unique([...self::DATA_TAGS, $name])));
        $safe = preg_replace('#<\s*/\s*(' . $names . ')\s*>#i', '&lt;/$1&gt;', $text);

        return "<{$name}>\n{$safe}\n</{$name}>";
    }
}
