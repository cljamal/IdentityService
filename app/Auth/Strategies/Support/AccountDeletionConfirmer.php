<?php

namespace App\Auth\Strategies\Support;

use App\Auth\AuthProviderName;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Request/confirm mechanics for proving the account holder still controls
 * a given channel before deletion proceeds. Keyed by user id, not by
 * identifier — we're confirming "it's still you", not looking anything up.
 */
class AccountDeletionConfirmer
{
    use GeneratesVerificationCode;

    public function __construct(private readonly OtpRepositoryInterface $otp) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, User $user, string $contact): void
    {
        $subject = $this->subject($provider, $user);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();
        $this->otp->put($subject, $code);

        // TODO: подключить реальный email/SMS-шлюз вместо лога.
        Log::info("Account deletion confirmation code for {$contact}: {$code}");
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, User $user, string $code): void
    {
        $subject = $this->subject($provider, $user);
        $actual = $this->otp->get($subject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $this->otp->forget($subject);
    }

    private function subject(AuthProviderName $provider, User $user): string
    {
        return "{$provider->value}-delete:{$user->id}";
    }
}
