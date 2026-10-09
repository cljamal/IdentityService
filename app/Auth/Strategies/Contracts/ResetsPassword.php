<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;
use App\Notifications\Otp\SmsTemplate;
use Closure;

/**
 * Additional contract for password-based providers that can reset a
 * forgotten password via a code. A provider with no delivery channel of
 * its own (e.g. a bare username) can still implement this if it has some
 * other way to notify the user (see UsernamePasswordStrategy's optional,
 * config-gated RescueContactResolver) — since PHP can't make interface
 * implementation conditional on runtime config, such a provider must
 * guard unsupported/unconfigured cases itself and throw
 * UnsupportedAuthOperationException, rather than simply not implementing
 * this interface.
 */
interface ResetsPassword
{
    /**
     * @return array<string, mixed>
     */
    public function passwordResetRequestRules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestPasswordReset(array $data, ?SmsTemplate $sms = null): void;

    /**
     * @return array<string, mixed>
     */
    public function passwordResetRules(): array;

    /**
     * @param  array<string, mixed>  $data
     * @param  Closure(User): void  $afterPasswordReset
     */
    public function resetPassword(array $data, Closure $afterPasswordReset): User;
}
