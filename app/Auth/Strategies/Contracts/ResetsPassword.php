<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

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
    public function passwordResetRequestRules(): array;

    public function requestPasswordReset(array $data): void;

    public function passwordResetRules(): array;

    public function resetPassword(array $data): User;
}
