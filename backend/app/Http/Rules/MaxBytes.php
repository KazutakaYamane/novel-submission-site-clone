<?php

namespace App\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 文字列の UTF-8 のバイト数の上限。組み込みの max は文字数(mb_strlen)で数える
 */
final readonly class MaxBytes implements ValidationRule
{
    public function __construct(
        private int $max,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->max) {
            $fail('validation.max_bytes')->translate(['max' => (string) $this->max]);
        }
    }
}
