<?php

namespace App\Auth\Rescue;

use App\Models\User;

/**
 * Resolves an out-of-band contact (e.g. email) to send a password-reset
 * code to, for identity providers that have no delivery channel of their
 * own (e.g. username/password). Null means no contact is on file.
 */
interface RescueContactResolver
{
    public function resolve(User $user): ?string;
}
