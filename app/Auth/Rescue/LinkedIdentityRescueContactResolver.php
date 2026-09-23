<?php

namespace App\Auth\Rescue;

use App\Auth\Enums\AuthProviderName;
use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;

/**
 * Default rescue path, needing zero configuration: a username+password
 * account often has a phone-otp or email-password identity linked to the
 * same user (nothing stops someone from registering via both) — if so,
 * that's a legitimate, already-proven-owned contact to send a password
 * reset code to. Only trusts a VERIFIED linked identity: an unverified
 * one could be something an attacker linked to themselves without ever
 * controlling it, which would turn "forgot password" into a takeover path.
 */
final readonly class LinkedIdentityRescueContactResolver implements RescueContactResolver
{
    public function __construct(private AuthProviderRepositoryInterface $providers) {}

    public function resolve(User $user): ?OtpDestination
    {
        foreach ([AuthProviderName::EmailPassword, AuthProviderName::PhoneOtp] as $provider) {
            $identity = $this->providers->findByUser($provider, $user);

            if ($identity && $identity->verified_at) {
                $channel = $provider === AuthProviderName::PhoneOtp ? OtpChannel::Phone : OtpChannel::Email;

                return new OtpDestination($channel, $identity->identifier);
            }
        }

        return null;
    }
}
