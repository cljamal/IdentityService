<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Repositories\Contracts\OtpRepositoryInterface;

/**
 * Shared throttled-OTP mechanics: check the resend cooldown and issue a
 * code, or verify one against what's stored. Callers own subject naming,
 * delivery (SMS/email/log) and when exactly a verified code is forgotten —
 * some flows need to hold off on that until later steps are known to
 * succeed, so it's a separate call rather than baked into verify().
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

    /**
     * @throws InvalidOtpException
     */
    public function verify(string $subject, string $code): void
    {
        $actual = $this->otp->get($subject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }
    }

    public function forget(string $subject): void
    {
        $this->otp->forget($subject);
    }
}
