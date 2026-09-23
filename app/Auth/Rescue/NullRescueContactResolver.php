<?php

namespace App\Auth\Rescue;

use App\Models\User;

/**
 * Default when no rescue table is configured. Strategies still gate the
 * feature explicitly (see UsernamePasswordStrategy::guardRescueEnabled),
 * this just guarantees a safe, always-constructible dependency.
 */
final class NullRescueContactResolver implements RescueContactResolver
{
    public function resolve(User $user): ?string
    {
        return null;
    }
}
