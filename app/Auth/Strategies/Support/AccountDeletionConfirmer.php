<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Enums\AuthProviderName;
use App\Events\Notifications\OtpCodeIssued;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Notifications\Otp\SmsTemplate;
use Closure;

/**
 * Request/confirm mechanics for proving the account holder still controls
 * a given channel before deletion proceeds. Keyed by user id, not by
 * identifier — we're confirming "it's still you", not looking anything up.
 */
final readonly class AccountDeletionConfirmer
{
    public function __construct(private OtpChallenge $challenge) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(AuthProviderName $provider, User $user, OtpDestination $destination, ?SmsTemplate $sms = null): void
    {
        $code = $this->challenge->request($this->subject($provider, $user));

        OtpCodeIssued::dispatch($destination, $code, OtpPurpose::AccountDeletion, $sms);
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     *
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, User $user, string $code, Closure $operation): mixed
    {
        $subject = $this->subject($provider, $user);

        return $this->challenge->consume($subject, $code, $operation);
    }

    private function subject(AuthProviderName $provider, User $user): string
    {
        return "{$provider->value}-delete:{$user->id}";
    }
}
