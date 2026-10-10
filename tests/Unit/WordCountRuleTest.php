<?php

namespace Tests\Unit;

use App\Rules\WordCountRule;
use PHPUnit\Framework\TestCase;

class WordCountRuleTest extends TestCase
{
    public function test_passes_when_word_count_is_under_limit(): void
    {
        $rule = new WordCountRule(5);

        $this->assertTrue($rule->passes('description', 'one two three'));
    }

    public function test_passes_when_word_count_equals_limit(): void
    {
        $rule = new WordCountRule(5);

        $this->assertTrue($rule->passes('description', 'one two three four five'));
    }

    public function test_fails_when_word_count_exceeds_limit_by_one(): void
    {
        $rule = new WordCountRule(5);

        $this->assertFalse($rule->passes('description', 'one two three four five six'));
    }

    public function test_strips_html_tags_before_counting_words(): void
    {
        $rule = new WordCountRule(3);

        $htmlUnder = '<p><strong>one</strong> <em>two</em> three</p>';
        $this->assertTrue($rule->passes('description', $htmlUnder));

        $htmlOver = '<p><strong>one</strong> <em>two</em> <span>three</span> <b>four</b></p>';
        $this->assertFalse($rule->passes('description', $htmlOver));
    }

    public function test_passes_for_empty_string(): void
    {
        $rule = new WordCountRule(5);

        $this->assertTrue($rule->passes('description', ''));
    }

    public function test_message_contains_max_word_limit(): void
    {
        $rule = new WordCountRule(10);

        $this->assertStringContainsString('10 words', $rule->message());
    }
}
