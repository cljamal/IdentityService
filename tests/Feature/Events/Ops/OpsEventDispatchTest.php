<?php

namespace Tests\Feature\Events\Ops;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Enums\SessionRevocationReason;
use App\Auth\Guards\IdApiGuard;
use App\Events\Ops\AccountDeleted;
use App\Events\Ops\RefreshTokenReuseDetected;
use App\Events\Ops\ServiceDisabled;
use App\Events\Ops\ServiceEnabled;
use App\Events\Ops\UserSessionRevoked;
use App\Models\AuthProvider;
use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class OpsEventDispatchTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_logout_dispatches_user_session_revoked(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        Event::fake([UserSessionRevoked::class]);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/logout')->assertOk();

        Event::assertDispatched(
            UserSessionRevoked::class,
            fn ($e) => $e->user->is($user) && $e->reason === SessionRevocationReason::Logout,
        );
    }

    public function test_explicit_session_revoke_dispatches_user_session_revoked(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        $sessionId = AuthSession::query()->where('user_id', $user->id)->value('id');

        Event::fake([UserSessionRevoked::class]);

        $this->withToken($tokens->accessToken)->deleteJson("/api/auth/sessions/{$sessionId}")->assertOk();

        Event::assertDispatched(
            UserSessionRevoked::class,
            fn ($e) => $e->user->is($user) && $e->reason === SessionRevocationReason::ExplicitRevoke,
        );
    }

    public function test_password_reset_dispatches_user_session_revoked(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        $user = User::factory()->for($this->defaultClient)->create();
        AuthProvider::query()->create([
            'user_id' => $user->id,
            'client_id' => $this->defaultClient->id,
            'provider' => AuthProviderName::EmailPassword->value,
            'identifier' => 'user@example.com',
            'meta' => ['password' => Hash::make('oldpassword123')],
            'verified_at' => now(),
        ]);

        $this->postJson('/api/auth/email-password/password/forgot', ['email' => 'user@example.com'])->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $identity = AuthProvider::query()->where('user_id', $user->id)->firstOrFail();
        $code = $otp->peek("{$this->defaultClient->id}:email-password-reset:{$identity->id}:user@example.com");

        Event::fake([UserSessionRevoked::class]);

        $this->postJson('/api/auth/email-password/password/reset', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        Event::assertDispatched(
            UserSessionRevoked::class,
            fn ($e) => $e->user->is($user) && $e->reason === SessionRevocationReason::PasswordReset,
        );
    }

    public function test_account_deletion_dispatches_account_deleted_not_session_revoked(): void
    {
        $this->app->singleton(OtpRepositoryInterface::class, FakeOtpRepository::class);

        $user = User::factory()->for($this->defaultClient)->create();
        AuthProvider::query()->create([
            'user_id' => $user->id,
            'client_id' => $this->defaultClient->id,
            'provider' => AuthProviderName::EmailPassword->value,
            'identifier' => 'delete-me@example.com',
            'meta' => ['password' => Hash::make('password123')],
            'verified_at' => now(),
        ]);
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->withToken($tokens->accessToken)
            ->postJson('/api/auth/email-password/account/delete')
            ->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek('email-password-delete:'.$user->id);

        Event::fake([AccountDeleted::class, UserSessionRevoked::class]);

        $this->withToken($tokens->accessToken)
            ->postJson('/api/auth/email-password/account/delete/confirm', ['code' => $code])
            ->assertOk();

        Event::assertDispatched(AccountDeleted::class, fn ($e) => $e->user->is($user));
        Event::assertNotDispatched(UserSessionRevoked::class);
    }

    public function test_refresh_token_reuse_dispatches_refresh_token_reuse_detected(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])->assertOk();

        Event::fake([RefreshTokenReuseDetected::class]);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'REFRESH_TOKEN_REUSED');

        Event::assertDispatched(RefreshTokenReuseDetected::class, fn ($e) => $e->user->is($user));
    }

    public function test_deactivating_a_client_dispatches_service_disabled(): void
    {
        Event::fake([ServiceDisabled::class]);

        $this->defaultClient->update(['is_active' => false]);

        Event::assertDispatched(ServiceDisabled::class, fn ($e) => $e->client->is($this->defaultClient));
    }

    public function test_reactivating_a_client_dispatches_service_enabled(): void
    {
        $this->defaultClient->update(['is_active' => false]);

        Event::fake([ServiceEnabled::class]);

        $this->defaultClient->update(['is_active' => true]);

        Event::assertDispatched(ServiceEnabled::class, fn ($e) => $e->client->is($this->defaultClient));
    }
}
