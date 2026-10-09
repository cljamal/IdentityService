<?php

namespace Tests\Unit\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Strategies\Support\AccountDeletionConfirmer;
use App\Auth\Strategies\Support\OtpChallenge;
use App\Exceptions\Auth\InvalidOtpException;
use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class AccountDeletionConfirmerTest extends TestCase
{
    public function test_confirm_accepts_the_requested_code_only_once(): void
    {
        $user = new User;
        $user->id = 1;

        $otp = new FakeOtpRepository;
        $confirmer = new AccountDeletionConfirmer(new OtpChallenge($otp));

        $confirmer->request(AuthProviderName::PhoneOtp, $user, new OtpDestination(OtpChannel::Phone, '998901234567'));
        $code = $otp->peek('phone-otp-delete:1');

        $confirmer->confirm(AuthProviderName::PhoneOtp, $user, $code, static function (): void {});

        $this->expectException(InvalidOtpException::class);

        // Same code again: already consumed by the confirm() above.
        $confirmer->confirm(AuthProviderName::PhoneOtp, $user, $code, static function (): void {});
    }

    public function test_confirm_rejects_a_wrong_code(): void
    {
        $user = new User;
        $user->id = 2;

        $otp = new FakeOtpRepository;
        $confirmer = new AccountDeletionConfirmer(new OtpChallenge($otp));

        $confirmer->request(AuthProviderName::PhoneOtp, $user, new OtpDestination(OtpChannel::Phone, '998901234567'));

        $this->expectException(InvalidOtpException::class);

        $confirmer->confirm(AuthProviderName::PhoneOtp, $user, '0000', static function (): void {});
    }
}
