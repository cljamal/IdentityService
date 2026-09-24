<?php

namespace App\Auth\Strategies\Support;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Events\Notifications\OtpCodeIssued;
use App\Events\Ops\UserPhoneChanged;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\NoLinkedIdentityException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;

/**
 * 3-step phone change: OTP to the OLD number proves the requester still
 * controls the account being changed, OTP to the NEW number proves they
 * actually control it, only then does the identifier change. The pending
 * new number itself is stashed in the same OTP store between steps (it's
 * just a short-lived string with a TTL, same shape as a code).
 */
final readonly class PhoneChangeCoordinator
{
    public function __construct(
        private OtpChallenge $challenge,
        private OtpRepositoryInterface $otp,
        private AuthProviderRepositoryInterface $providers,
        private IdentityChangeLogRepositoryInterface $history,
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

        $code = $this->challenge->request($this->oldSubject($user));
        $this->otp->put($this->pendingSubject($user), $newPhone);

        OtpCodeIssued::dispatch(
            new OtpDestination(OtpChannel::Phone, $identity->identifier),
            $code,
            OtpPurpose::IdentifierChangeOld,
        );
    }

    /**
     * @throws InvalidOtpException
     * @throws OtpThrottledException
     */
    public function confirmOld(User $user, string $code): void
    {
        $this->challenge->verify($this->oldSubject($user), $code);

        $newPhone = $this->otp->get($this->pendingSubject($user));

        if ($newPhone === null) {
            throw new InvalidOtpException;
        }

        $newCode = $this->challenge->request($this->newSubject($user));

        // Re-stash the pending number so its TTL restarts alongside the NEW
        // code issued above — otherwise it would still expire on the OLD
        // code's original TTL window, before the user can ever reach it.
        $this->otp->put($this->pendingSubject($user), $newPhone);

        // Старый код одноразовый — подтверждён, больше не нужен.
        $this->challenge->forget($this->oldSubject($user));

        OtpCodeIssued::dispatch(
            new OtpDestination(OtpChannel::Phone, $newPhone),
            $newCode,
            OtpPurpose::IdentifierChangeNew,
        );
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirmNew(User $user, string $code): void
    {
        $this->challenge->verify($this->newSubject($user), $code);

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

        UserPhoneChanged::dispatch($user, $oldPhone, $newPhone);

        $this->challenge->forget($this->newSubject($user));
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
