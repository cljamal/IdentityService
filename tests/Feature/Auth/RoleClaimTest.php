<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\RoleName;
use App\Auth\Guards\IdApiGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActsAsClient;
use Tests\TestCase;

class RoleClaimTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_role_claim_is_null_for_a_user_without_a_role(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->withToken($tokens->accessToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', null);
    }

    public function test_role_claim_survives_a_refresh(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $user->assignRole(Role::findOrCreate(RoleName::User, 'id-api'));

        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $refreshed = $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken]);
        $refreshed->assertOk();

        $this->withToken($refreshed->json('data.access_token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', RoleName::User->value);
    }

    /**
     * Regression test: refreshUsingToken() mints the new access token via
     * $this->jwt->fromUser($user), which always re-derives custom claims
     * from getJWTCustomClaims() — unlike jwt-auth's own refresh(), which
     * only persists claims listed in config('jwt.persistent_claims').
     * Without that, a role granted after the original login would never
     * show up until the user logs in again from scratch.
     */
    public function test_refresh_picks_up_a_role_granted_after_the_original_login(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $user->assignRole(Role::findOrCreate(RoleName::User, 'id-api'));

        $refreshed = $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken]);
        $refreshed->assertOk();

        $this->withToken($refreshed->json('data.access_token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', RoleName::User->value);
    }
}
