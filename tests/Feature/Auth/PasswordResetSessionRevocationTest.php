<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Models\AuthProvider;
use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\FakeOtpRepository;
use Tests\TestCase;

class PasswordResetSessionRevocationTest extends TestCase
{
    use RefreshDatabase, ActsAsClient;

    /**
     * A refresh token is a long-lived (14-day) bearer credential now, so a
     * password reset must not leave a pre-reset session (and the refresh
     * token that comes with it) usable afterwards — otherwise a stolen
     * refresh token survives the very password change meant to lock it out.
     */
    public function test_resetting_a_password_revokes_every_session_that_predates_it(): void
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

        IdApiGuard::current()->loginWithRefreshToken($user);
        $originalSessionId = AuthSession::query()->where('user_id', $user->id)->value('id');

        $this->postJson('/api/auth/email-password/password/forgot', ['email' => 'user@example.com'])
            ->assertOk();

        /** @var FakeOtpRepository $otp */
        $otp = $this->app->make(OtpRepositoryInterface::class);
        $code = $otp->peek("{$this->defaultClient->id}:email-password-reset:user@example.com");

        $this->postJson('/api/auth/email-password/password/reset', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertNotNull(AuthSession::query()->find($originalSessionId)->revoked_at);

        // The reset itself logs the user back in — exactly one live
        // session (the new one) should remain.
        $this->assertSame(
            1,
            AuthSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->count(),
        );
    }
}
