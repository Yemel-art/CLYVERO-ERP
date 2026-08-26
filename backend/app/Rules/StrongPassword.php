<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces the password policy from Security Blueprint §7.
 *
 * Required: ≥12 chars, ≥1 uppercase, ≥1 lowercase, ≥1 digit, ≥1 special.
 */
class StrongPassword implements ValidationRule
{
    private const MIN_LENGTH = 12;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $attributeKey = 'validation.attributes.' . $attribute;
        $attributeLabel = __($attributeKey);
        if ($attributeLabel === $attributeKey) {
            $attributeLabel = str_replace('_', ' ', $attribute);
        }

        if (! is_string($value)) {
            $fail(__('validation.string', ['attribute' => $attributeLabel]));
            return;
        }

        $checks = [
            __('password_policy.minimum', ['count' => self::MIN_LENGTH]) => strlen($value) >= self::MIN_LENGTH,
            __('password_policy.uppercase') => (bool) preg_match('/[A-Z]/', $value),
            __('password_policy.lowercase') => (bool) preg_match('/[a-z]/', $value),
            __('password_policy.number') => (bool) preg_match('/\d/', $value),
            __('password_policy.special') => (bool) preg_match('/[^A-Za-z0-9]/', $value),
        ];

        $missing = array_keys(array_filter($checks, fn (bool $ok) => ! $ok));
        if ($missing !== []) {
            $fail(__('password_policy.invalid', [
                'attribute' => $attributeLabel,
                'requirements' => implode(__('password_policy.joiner'), $missing),
            ]));
        }
    }
}
