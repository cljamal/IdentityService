<?php

namespace App\Auth\Strategies\Support;

use App\Auth\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Concerns\GeneratesVerificationCode;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * 3-step phone change: OTP to the OLD number proves the requester still
 * controls the account being changed, OTP to the NEW number proves they
 * actually control it, only then does the identifier change. The pending
 * new number itself is stashed in the same OTP store between steps (it's
 * just a short-lived string with a TTL, same shape as a code).
 */
class PhoneChangeCoordinator
{
    use GeneratesVerificationCode;

    public function __construct(
        private readonly OtpRepositoryInterface $otp,
        private readonly AuthProviderRepositoryInterface $providers,
        private readonly IdentityChangeLogRepositoryInterface $history,
    ) {}

    /**
     * @throws NoLinkedIdentityException
     * @throws OtpThrottledException
     */
    public function requestChange(User $user, string $newPhone): void
    {
        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

        if (! $identity) {
            throw new NoLinkedIdentityException(AuthProviderName::PhoneOtp->value);
        }

        $oldSubject = $this->oldSubject($user);

        if (! $this->otp->canBeRequested($oldSubject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($oldSubject));
        }

        $code = $this->generateCode();
        $this->otp->put($oldSubject, $code);
        $this->otp->put($this->pendingSubject($user), $newPhone);

        // TODO: подключить реальный SMS-шлюз вместо лога.
        Log::info("Phone change: code for OLD number {$identity->identifier}: {$code}");
    }

    /**
     * @throws InvalidOtpException
     * @throws OtpThrottledException
     */
    public function confirmOld(User $user, string $code): void
    {
        $oldSubject = $this->oldSubject($user);
        $actual = $this->otp->get($oldSubject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $newPhone = $this->otp->get($this->pendingSubject($user));

        if ($newPhone === null) {
            throw new InvalidOtpException;
        }

        $newSubject = $this->newSubject($user);

        if (! $this->otp->canBeRequested($newSubject)) {
            throw new OtpThrottledException($this->otp->secondsUntilNextRequest($newSubject));
        }

        // Старый код одноразовый — подтверждён, больше не нужен.
        $this->otp->forget($oldSubject);

        $newCode = $this->generateCode();
        $this->otp->put($newSubject, $newCode);

        // TODO: подключить реальный SMS-шлюз вместо лога.
        Log::info("Phone change: code for NEW number {$newPhone}: {$newCode}");
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirmNew(User $user, string $code): void
    {
        $newSubject = $this->newSubject($user);
        $actual = $this->otp->get($newSubject);

        if ($actual === null || ! hash_equals($actual, $code)) {
            throw new InvalidOtpException;
        }

        $newPhone = $this->otp->get($this->pendingSubject($user));

        if ($newPhone === null) {
            throw new InvalidOtpException;
        }

        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

        if (! $identity) {
            throw new InvalidOtpException;
        }

        $oldPhone = $identity->identifier;

        $this->providers->changeIdentifier($identity, $newPhone);

        $this->history->log($user, AuthProviderName::PhoneOtp, IdentityChangeAction::IdentifierChanged, $oldPhone, $newPhone);

        $this->otp->forget($newSubject);
        $this->otp->forget($this->pendingSubject($user));
    }

    private function oldSubject(User $user): string
    {
        return "phone-otp-change-old:{$user->id}";
    }

    private function newSubject(User $user): string
    {
        return "phone-otp-change-new:{$user->id}";
    }

    private function pendingSubject(User $user): string
    {
        return "phone-otp-change-pending:{$user->id}";
    }
}
