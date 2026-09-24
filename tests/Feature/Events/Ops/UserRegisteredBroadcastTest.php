<?php

namespace Tests\Feature\Events\Ops;

use App\Auth\Enums\AuthProviderName;
use App\Events\Ops\UserRegistered;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

/**
 * UserRegistered fires from 3 different places, not 1 — see PhoneOtpStrategy
 * ::authenticate(), RegisterAction::handle(), VerifyRegistrationAction
 * ::handle(). Each covers a distinct "became live" path; missing any one of
 * them silently drops that path's broadcast.
 */
class UserRegisteredBroadcastTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_phone_otp_auto_registration_dispatches_user_registered(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$this->defaultClient->id}:998901234567");

        Event::fake([UserRegistered::class]);

        $this->postJson('/api/auth/phone-otp/login', ['phone' => '998901234567', 'code' => $code])
            ->assertOk();

        Event::assertDispatched(
            UserRegistered::class,
            fn ($e) => $e->provider === AuthProviderName::PhoneOtp,
        );
    }

    public function test_phone_otp_returning_login_does_not_dispatch_user_registered(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])->assertOk();
        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $this->postJson('/api/auth/phone-otp/login', [
            'phone' => '998901234567',
            'code' => $otp->peek("{$this->defaultClient->id}:998901234567"),
        ])->assertOk();

        // Second login, same (now-existing) phone number.
        $this->postJson('/api/auth/phone-otp/otp', ['phone' => '998901234567'])->assertOk();

        Event::fake([UserRegistered::class]);

        $this->postJson('/api/auth/phone-otp/login', [
            'phone' => '998901234567',
            'code' => $otp->peek("{$this->defaultClient->id}:998901234567"),
        ])->assertOk();

        Event::assertNotDispatched(UserRegistered::class);
    }

    public function test_username_password_registration_without_a_rescue_contact_dispatches_user_registered(): void
    {
        Event::fake([UserRegistered::class]);

        $this->postJson('/api/auth/username-password/register', [
            'username' => 'auto_verified_user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        Event::assertDispatched(
            UserRegistered::class,
            fn ($e) => $e->provider === AuthProviderName::UsernamePassword,
        );
    }

    public function test_email_password_registration_dispatches_user_registered_only_after_verification(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        Event::fake([UserRegistered::class]);

        $this->postJson('/api/auth/email-password/register', [
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        Event::assertNotDispatched(UserRegistered::class);

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$this->defaultClient->id}:email-password-verify:new@example.com");

        $this->postJson('/api/auth/email-password/register/verify', [
            'email' => 'new@example.com',
            'code' => $code,
        ])->assertOk();

        Event::assertDispatched(
            UserRegistered::class,
            fn ($e) => $e->provider === AuthProviderName::EmailPassword,
        );
    }
}
