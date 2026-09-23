<?php

namespace App\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects a (already normalized, digits-only) phone that doesn't start
 * with one of the enabled country codes from config/identity.php.
 */
final class AllowedPhoneCountry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->isAllowed($value)) {
            $fail('Регистрация и вход с номеров этой страны недоступны.');
        }
    }

    private function isAllowed(string $phone): bool
    {
        foreach (config('identity.phone_countries', []) as $country) {
            if (($country['enabled'] ?? false) && str_starts_with($phone, (string) $country['code'])) {
                return true;
            }
        }

        return false;
    }
}
