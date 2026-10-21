<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class WordCountRule implements Rule
{
    protected $maxWords;

    /**
     * Create a new rule instance.
     *
     * @param  int  $maxWords
     * @return void
     */
    public function __construct($maxWords)
    {
        $this->maxWords = $maxWords;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $wordCount = str_word_count(strip_tags($value));

        return $wordCount <= $this->maxWords;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "The :attribute must not be greater than {$this->maxWords} words.";
    }
}
