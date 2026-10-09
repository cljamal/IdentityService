<?php

namespace Tests\Unit\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Support\OtpChallenge;
use App\Auth\Strategies\Support\PhoneChangeCoordinator;
use App\Events\Notifications\OtpCodeIssued;
use App\Events\Ops\UserPhoneChanged;
use App\Exceptions\Auth\InvalidOtpException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class PhoneChangeCoordinatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Regression test for the pending-number TTL bug: confirmOld() must
     * refresh the pending new-number's TTL when it issues the new code,
     * otherwise it expires on the OLD code's original window and
     * confirmNew() fails even though the user entered the right code.
     */
    public function test_confirm_new_succeeds_after_the_original_request_window_has_elapsed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
        Event::fake([UserPhoneChanged::class]);

        $user = new User;
        $user->id = 1;

        $identity = new AuthProvider(['identifier' => '998901234567']);

        /** @var AuthProviderRepositoryInterface&MockInterface $providers */
        $providers = Mockery::mock(AuthProviderRepositoryInterface::class);
        $providers->shouldReceive('findByUser')
            ->with(AuthProviderName::PhoneOtp, $user)
            ->andReturn($identity);
        $providers->shouldReceive('changeIdentifier')
            ->once()
            ->with($identity, '998907654321');

        /** @var IdentityChangeLogRepositoryInterface&MockInterface $history */
        $history = Mockery::mock(IdentityChangeLogRepositoryInterface::class);
        $history->shouldReceive('log')
            ->once()
            ->with($user, AuthProviderName::PhoneOtp, IdentityChangeAction::IdentifierChanged, '998901234567', '998907654321');

        $otp = new FakeOtpRepository;
        $coordinator = new PhoneChangeCoordinator(new OtpChallenge($otp), $otp, $providers, $history);

        $coordinator->requestChange($user, '998907654321');
        $oldCode = $otp->peekLatest('phone-otp-change-old:1:');

        // 80s later: still inside the old code's 90s TTL.
        Carbon::setTestNow(Carbon::now()->addSeconds(80));
        $coordinator->confirmOld($user, $oldCode);
        $newCode = $otp->peekLatest('phone-otp-change-new:1:');

        // Another 80s later: 160s after requestChange() overall — past the
        // pending value's original (un-refreshed) 90s TTL, but still inside
        // the fresh 90s TTL the new code got at confirmOld() time.
        Carbon::setTestNow(Carbon::now()->addSeconds(80));
        $coordinator->confirmNew($user, $newCode);

        Event::assertDispatched(UserPhoneChanged::class, function (UserPhoneChanged $event) use ($user): bool {
            return $event->user === $user
                && $event->oldPhone === '998901234567'
                && $event->newPhone === '998907654321';
        });
    }

    public function test_confirm_new_rejects_a_wrong_code(): void
    {
        $user = new User;
        $user->id = 2;

        $identity = new AuthProvider(['identifier' => '998901234567']);

        /** @var AuthProviderRepositoryInterface&MockInterface $providers */
        $providers = Mockery::mock(AuthProviderRepositoryInterface::class);
        $providers->shouldReceive('findByUser')->andReturn($identity);

        /** @var IdentityChangeLogRepositoryInterface&MockInterface $history */
        $history = Mockery::mock(IdentityChangeLogRepositoryInterface::class);

        $otp = new FakeOtpRepository;
        $coordinator = new PhoneChangeCoordinator(new OtpChallenge($otp), $otp, $providers, $history);

        $coordinator->requestChange($user, '998907654321');
        $coordinator->confirmOld($user, $otp->peekLatest('phone-otp-change-old:2:'));

        $this->expectException(InvalidOtpException::class);

        $coordinator->confirmNew($user, '0000');
    }

    public function test_code_for_previous_phone_change_attempt_cannot_change_to_a_new_number(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

        $user = new User;
        $user->id = 3;
        $identity = new AuthProvider(['identifier' => '998901234567']);

        /** @var AuthProviderRepositoryInterface&MockInterface $providers */
        $providers = Mockery::mock(AuthProviderRepositoryInterface::class);
        $providers->shouldReceive('findByUser')->andReturn($identity);
        $providers->shouldNotReceive('changeIdentifier');

        /** @var IdentityChangeLogRepositoryInterface&MockInterface $history */
        $history = Mockery::mock(IdentityChangeLogRepositoryInterface::class);
        $history->shouldNotReceive('log');

        $otp = new FakeOtpRepository;
        $coordinator = new PhoneChangeCoordinator(new OtpChallenge($otp), $otp, $providers, $history);

        $coordinator->requestChange($user, '998907654321');
        $coordinator->confirmOld($user, $otp->peekLatest('phone-otp-change-old:3:'));
        $codeForA = $otp->peekLatest('phone-otp-change-new:3:');

        Carbon::setTestNow(Carbon::now()->addSeconds(60));
        $coordinator->requestChange($user, '998909876543');

        try {
            $coordinator->confirmNew($user, $codeForA);
            $this->fail('A code from the previous attempt must be rejected.');
        } catch (InvalidOtpException) {
            $this->assertSame('998901234567', $identity->identifier);
        }
    }

    public function test_old_number_sms_cooldown_is_stable_and_throttled_attempt_preserves_pending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
        Event::fake([OtpCodeIssued::class]);

        $user = new User;
        $user->id = 4;
        $otherUser = new User;
        $otherUser->id = 5;
        $identity = new AuthProvider(['identifier' => '998901234567']);

        /** @var AuthProviderRepositoryInterface&MockInterface $providers */
        $providers = Mockery::mock(AuthProviderRepositoryInterface::class);
        $providers->shouldReceive('findByUser')->andReturn($identity);

        /** @var IdentityChangeLogRepositoryInterface&MockInterface $history */
        $history = Mockery::mock(IdentityChangeLogRepositoryInterface::class);
        $otp = new FakeOtpRepository;
        $coordinator = new PhoneChangeCoordinator(new OtpChallenge($otp), $otp, $providers, $history);

        $coordinator->requestChange($user, '998907654321');
        $firstPending = $otp->peek('phone-otp-change-pending:4');
        $this->assertNotNull($firstPending);
        $firstAttempt = json_decode($firstPending, true, flags: JSON_THROW_ON_ERROR)['attempt'];
        $oldCode = $otp->peek("phone-otp-change-old:4:{$firstAttempt}");
        Event::assertDispatchedTimes(OtpCodeIssued::class, 1);

        try {
            $coordinator->requestChange($user, '998909876543');
            $this->fail('An immediate repeat must be throttled.');
        } catch (OtpThrottledException) {
            $this->assertSame($firstPending, $otp->peek('phone-otp-change-pending:4'));
            Event::assertDispatchedTimes(OtpCodeIssued::class, 1);
        }

        $coordinator->requestChange($otherUser, '998900000001');
        $this->assertNotNull($otp->peek('phone-otp-change-pending:5'));
        Event::assertDispatchedTimes(OtpCodeIssued::class, 2);

        Carbon::setTestNow(Carbon::now()->addSeconds(60));
        $coordinator->requestChange($user, '998909876543');
        $currentPending = $otp->peek('phone-otp-change-pending:4');
        $newPending = json_decode($currentPending, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('998909876543', $newPending['phone']);

        try {
            $coordinator->confirmOld($user, $oldCode);
            $this->fail('A code from the earlier attempt must not confirm the new attempt.');
        } catch (InvalidOtpException) {
            $this->assertSame('998909876543', $newPending['phone']);
        }
    }
}
