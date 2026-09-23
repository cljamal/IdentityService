<?php

namespace Tests\Unit\Auth;

use App\Auth\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Auth\Strategies\Support\OtpChallenge;
use App\Auth\Strategies\Support\PhoneChangeCoordinator;
use App\Exceptions\Auth\InvalidOtpException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Support\Carbon;
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

        $user = new User;
        $user->id = 1;

        $identity = new AuthProvider(['identifier' => '998901234567']);

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
        $oldCode = $otp->peek('phone-otp-change-old:1');

        // 80s later: still inside the old code's 90s TTL.
        Carbon::setTestNow(Carbon::now()->addSeconds(80));
        $coordinator->confirmOld($user, $oldCode);
        $newCode = $otp->peek('phone-otp-change-new:1');

        // Another 80s later: 160s after requestChange() overall — past the
        // pending value's original (un-refreshed) 90s TTL, but still inside
        // the fresh 90s TTL the new code got at confirmOld() time.
        Carbon::setTestNow(Carbon::now()->addSeconds(80));
        $coordinator->confirmNew($user, $newCode);
    }

    public function test_confirm_new_rejects_a_wrong_code(): void
    {
        $user = new User;
        $user->id = 2;

        $identity = new AuthProvider(['identifier' => '998901234567']);

        $providers = Mockery::mock(AuthProviderRepositoryInterface::class);
        $providers->shouldReceive('findByUser')->andReturn($identity);

        $history = Mockery::mock(IdentityChangeLogRepositoryInterface::class);

        $otp = new FakeOtpRepository;
        $coordinator = new PhoneChangeCoordinator(new OtpChallenge($otp), $otp, $providers, $history);

        $coordinator->requestChange($user, '998907654321');
        $coordinator->confirmOld($user, $otp->peek('phone-otp-change-old:2'));

        $this->expectException(InvalidOtpException::class);

        $coordinator->confirmNew($user, '0000');
    }
}
