<?php

namespace App\Auth\Strategies\Support;

use App\Models\User;

/**
 * Outcome of register(): the identity might already be verified (e.g.
 * phone OTP-style providers, or a password provider with no channel to
 * verify against) or still pending a confirmation code — the caller uses
 * `verified` to decide whether to issue a token now or wait for it.
 */
final class RegistrationResult
{
    public function __construct(
        public readonly User $user,
        public readonly bool $verified,
    ) {
    }
}
