<?php

namespace App\Auth\Rescue;

use App\Models\User;
use App\Notifications\Otp\OtpDestination;

/**
 * Always-empty resolver — a safe, always-constructible base case for
 * ChainedRescueContactResolver when nothing else is configured.
 */
final class NullRescueContactResolver implements RescueContactResolver
{
    public function resolve(User $user): ?OtpDestination
    {
        return null;
    }
}
