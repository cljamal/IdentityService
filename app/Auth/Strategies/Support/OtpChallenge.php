<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Exceptions\Auth\OtpThrottledException;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Closure;

/**
 * Shared throttled-OTP mechanics: check the resend cooldown and issue a
 * code, or atomically consume one while its protected operation runs.
 */
final readonly class OtpChallenge
{
    use GeneratesVerificationCode;

    public function __construct(private OtpRepositoryInterface $otp) {}

    /**
     * @throws OtpThrottledException
     */
    public function request(string $subject): string
    {
        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();
        $this->otp->put($subject, $code);

        return $code;
    }

    /** @throws OtpThrottledException */
    public function requestWithCooldownAndPending(
        string $challengeSubject,
        string $cooldownSubject,
        string $pendingSubject,
        string $pendingValue,
    ): string {
        $code = $this->generateCode();
        $retryAfter = $this->otp->issueWithCooldownAndReplacePending(
            $challengeSubject,
            $code,
            $cooldownSubject,
            $pendingSubject,
            $pendingValue,
        );

        if ($retryAfter > 0) {
            throw new OtpThrottledException($retryAfter);
        }

        return $code;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function consume(string $subject, string $code, Closure $operation): mixed
    {
        return $this->otp->consume($subject, $code, $operation);
    }
}
