<?php

namespace App\Auth\Rescue;

use App\Models\User;
use App\Notifications\Otp\OtpDestination;

/**
 * Tries each resolver in order, returns the first hit. Lets
 * AppServiceProvider compose "linked identity, then the configured
 * rescue table, then nothing" without any one resolver needing to know
 * about the others.
 */
final readonly class ChainedRescueContactResolver implements RescueContactResolver
{
    /**
     * @param  array<RescueContactResolver>  $resolvers
     */
    public function __construct(private array $resolvers) {}

    public function resolve(User $user): ?OtpDestination
    {
        foreach ($this->resolvers as $resolver) {
            $destination = $resolver->resolve($user);

            if ($destination !== null) {
                return $destination;
            }
        }

        return null;
    }
}
