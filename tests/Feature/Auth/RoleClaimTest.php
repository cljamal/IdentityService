<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\RoleName;
use App\Auth\Guards\IdApiGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_claim_is_null_for_a_user_without_a_role(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', null);
    }

    public function test_role_claim_survives_a_refresh(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RoleName::User, 'id-api'));

        $token = IdApiGuard::current()->login($user);

        $refreshed = $this->withToken($token)->postJson('/api/auth/refresh');
        $refreshed->assertOk();

        $this->withToken($refreshed->json('data.access_token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', RoleName::User->value);
    }

    /**
     * Regression test: buildRefreshClaims() in the underlying jwt-auth
     * library does not call getJWTCustomClaims() again on refresh, so
     * without IdApiGuard::refresh() explicitly re-deriving it, a role
     * granted after the original login would never show up until the
     * user logs in again from scratch.
     */
    public function test_refresh_picks_up_a_role_granted_after_the_original_login(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);

        $user->assignRole(Role::findOrCreate(RoleName::User, 'id-api'));

        $refreshed = $this->withToken($token)->postJson('/api/auth/refresh');
        $refreshed->assertOk();

        $this->withToken($refreshed->json('data.access_token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('claims.role', RoleName::User->value);
    }
}
