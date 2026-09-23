<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Enums\AuthProviderName;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Request/confirm mechanics for proving the account holder still controls
 * a given channel before deletion proceeds. Keyed by user id, not by
 * identifier — we're confirming "it's still you", not looking anything up.
 */
class AccountDeletionConfirmer
{
    public function __construct(private readonly OtpChallenge $challenge) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, User $user, string $contact): void
    {
        $code = $this->challenge->request($this->subject($provider, $user));

        // TODO: подключить реальный email/SMS-шлюз вместо лога.
        Log::info("Account deletion confirmation code for {$contact}: {$code}");
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, User $user, string $code): void
    {
        $subject = $this->subject($provider, $user);

        $this->challenge->verify($subject, $code);
        $this->challenge->forget($subject);
    }

    private function subject(AuthProviderName $provider, User $user): string
    {
        return "{$provider->value}-delete:{$user->id}";
    }
}
