<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Events\Notifications\OtpCodeIssued;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;

/**
 * Request/confirm mechanics for proving ownership of an identifier right
 * after register(). Same code-based primitive as CodeBasedPasswordReset,
 * kept as its own class because the outcome differs (marks the identity
 * verified, never touches the password) — and unlike password reset,
 * this is only ever called for an identity we just created ourselves in
 * the same request, so there's no anti-enumeration concern here.
 */
final readonly class RegistrationVerifier
{
    use GeneratesVerificationCode;

    public function __construct(
        private OtpRepositoryInterface $otp,
        private AuthProviderRepositoryInterface $providers,
        private IdentityChangeLogRepositoryInterface $history,
    ) {}

    /**
     * @throws OtpThrottledException
     */
    public function send(AuthProviderName $provider, string $identifier, OtpDestination $destination): void
    {
        $subject = $this->subject($provider, $identifier);

        if (! $this->otp->canBeRequested($subject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($subject));
        }

        $code = $this->generateCode();
        $this->otp->put($subject, $code);

        OtpCodeIssued::dispatch($destination, $code, OtpPurpose::Registration);
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirm(AuthProviderName $provider, string $identifier, string $code): User
    {
        $subject = $this->subject($provider, $identifier);
        $actual = $this->otp->get($subject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $identity = $this->providers->findByIdentifier($provider, $identifier);

        if (! $identity) {
            throw new InvalidOtpException;
        }

        $user = $identity->userOrFail();

        $this->providers->markVerified($provider, $user);

        $this->history->log($user, $provider, IdentityChangeAction::Verified, null, $identifier);

        $this->otp->forget($subject);

        return $user;
    }

    private function subject(AuthProviderName $provider, string $identifier): string
    {
        return "{$provider->value}-verify:{$identifier}";
    }
}
