<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Matrix row 4.7: the two poles of one axis are opposite end states, so they may not be the same
 * label, ignoring case and surrounding or repeated spaces ("Favorable" and " favorable").
 */
class DifferentPole implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(protected string $otherField) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $other = data_get($this->data, $this->otherField);
        if (is_string($value) && is_string($other) && self::normalize($value) === self::normalize($other)) {
            $fail('Kedua kutub satu sumbu harus berbeda.');
        }
    }

    public static function normalize(string $label): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label)));
    }
}
