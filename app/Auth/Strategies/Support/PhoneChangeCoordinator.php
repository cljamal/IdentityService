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
use App\Notifications\Otp\SmsTemplate;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    public function requestChange(User $user, string $newPhone, ?SmsTemplate $sms = null): void
    {
        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

        if (! $identity) {
            throw new NoLinkedIdentityException(AuthProviderName::PhoneOtp->value);
        }

        $attempt = (string) Str::uuid();
        $pending = json_encode([
            'attempt' => $attempt,
            'phone' => $newPhone,
        ], JSON_THROW_ON_ERROR);

        $code = $this->challenge->requestWithCooldownAndPending(
            $this->oldSubject($user, $attempt),
            $this->oldCooldownSubject($user),
            $this->pendingSubject($user),
            $pending,
        );

        OtpCodeIssued::dispatch(
            new OtpDestination(OtpChannel::Phone, $identity->identifier),
            $code,
            OtpPurpose::IdentifierChangeOld,
            $sms,
        );
    }

    /**
     * @throws InvalidOtpException
     * @throws OtpThrottledException
     */
    public function confirmOld(User $user, string $code, ?SmsTemplate $sms = null): void
    {
        $pending = $this->pending($user);

        if ($pending === null) {
            throw new InvalidOtpException;
        }

        $this->challenge->consume(
            $this->oldSubject($user, $pending['attempt']),
            $code,
            function () use ($user, $pending, $sms): void {
                $newSubject = $this->newSubject($user, $pending['attempt']);
                $newCode = $this->otp->get($newSubject)
                    ?? $this->challenge->request($newSubject);
                if (! $this->otp->extendTtlIfCurrent(
                    $this->pendingSubject($user),
                    json_encode($pending, JSON_THROW_ON_ERROR),
                )) {
                    throw new InvalidOtpException;
                }

                OtpCodeIssued::dispatch(
                    new OtpDestination(OtpChannel::Phone, $pending['phone']),
                    $newCode,
                    OtpPurpose::IdentifierChangeNew,
                    $sms,
                );
            },
        );
    }

    /**
     * @throws InvalidOtpException
     */
    public function confirmNew(User $user, string $code): void
    {
        $pending = $this->pending($user);

        if ($pending === null) {
            throw new InvalidOtpException;
        }

        $newPhone = $pending['phone'];

        $pendingValue = json_encode($pending, JSON_THROW_ON_ERROR);
        $this->otp->consume(
            $this->pendingSubject($user),
            $pendingValue,
            function () use ($user, $pending, $newPhone, $code): void {
                $this->challenge->consume(
                    $this->newSubject($user, $pending['attempt']),
                    $code,
                    function () use ($user, $newPhone): void {
                        $identity = $this->providers->findByUser(AuthProviderName::PhoneOtp, $user);

                        if (! $identity) {
                            throw new InvalidOtpException;
                        }

                        $oldPhone = $identity->identifier;

                        DB::transaction(function () use ($identity, $user, $oldPhone, $newPhone): void {
                            $this->providers->changeIdentifier($identity, $newPhone);
                            $this->history->log($user, AuthProviderName::PhoneOtp, IdentityChangeAction::IdentifierChanged, $oldPhone, $newPhone);
                        });

                        UserPhoneChanged::dispatch($user, $oldPhone, $newPhone);
                    },
                );
            },
        );
    }

    /**
     * @return array{attempt: string, phone: string}|null
     */
    private function pending(User $user): ?array
    {
        $value = $this->otp->get($this->pendingSubject($user));

        if ($value === null) {
            return null;
        }

        $pending = json_decode($value, true);

        return is_array($pending)
            && isset($pending['attempt'], $pending['phone'])
            && is_string($pending['attempt'])
            && is_string($pending['phone'])
            ? ['attempt' => $pending['attempt'], 'phone' => $pending['phone']]
            : null;
    }

    private function oldSubject(User $user, string $attempt): string
    {
        return "phone-otp-change-old:{$user->id}:{$attempt}";
    }

    private function newSubject(User $user, string $attempt): string
    {
        return "phone-otp-change-new:{$user->id}:{$attempt}";
    }

    private function pendingSubject(User $user): string
    {
        return "phone-otp-change-pending:{$user->id}";
    }

    private function oldCooldownSubject(User $user): string
    {
        return "phone-otp-change-old-cooldown:{$user->id}";
    }
}
