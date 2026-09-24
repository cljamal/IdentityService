<?php

namespace Tests\Feature\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_records_a_session(): void
    {
        $user = User::factory()->create();

        IdApiGuard::current()->login($user);

        $this->assertDatabaseCount('auth_sessions', 1);
        $this->assertDatabaseHas('auth_sessions', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_refresh_rotates_the_existing_session_instead_of_creating_a_new_one(): void
    {
        $user = User::factory()->create();
        $tokens = IdApiGuard::current()->login($user);

        $originalJti = AuthSession::query()->where('user_id', $user->id)->value('jti');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])->assertOk();

        $this->assertDatabaseCount('auth_sessions', 1);
        $this->assertNotSame(
            $originalJti,
            AuthSession::query()->where('user_id', $user->id)->value('jti'),
        );
    }

    public function test_refresh_rejects_an_already_rotated_refresh_token(): void
    {
        $user = User::factory()->create();
        $tokens = IdApiGuard::current()->login($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])->assertOk();

        // The original refresh_token was already rotated away above —
        // presenting it again looks like a leaked token, not just a stale one.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'REFRESH_TOKEN_REUSED');

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
    }

    public function test_logout_marks_the_session_revoked(): void
    {
        $user = User::factory()->create();
        $tokens = IdApiGuard::current()->login($user);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/logout')->assertOk();

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
    }

    public function test_can_list_sessions_with_the_current_one_flagged(): void
    {
        $user = User::factory()->create();
        $tokens = IdApiGuard::current()->login($user);

        $response = $this->withToken($tokens->accessToken)->getJson('/api/auth/sessions');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertTrue($response->json('data.0.is_current'));
    }

    public function test_revoking_a_session_immediately_invalidates_its_token(): void
    {
        $user = User::factory()->create();
        $tokens = IdApiGuard::current()->login($user);
        $sessionId = AuthSession::query()->where('user_id', $user->id)->value('id');

        $this->withToken($tokens->accessToken)->deleteJson("/api/auth/sessions/{$sessionId}")->assertOk();

        // The token itself is still cryptographically valid and unexpired —
        // only our own revocation record makes it stop working.
        $this->withToken($tokens->accessToken)->getJson('/api/auth/sessions')->assertUnauthorized();
    }

    public function test_revoking_another_users_session_looks_like_not_found(): void
    {
        $owner = User::factory()->create();
        IdApiGuard::current()->login($owner);
        $sessionId = AuthSession::query()->where('user_id', $owner->id)->value('id');

        $intruder = User::factory()->create();
        $intruderTokens = IdApiGuard::current()->login($intruder);

        $this->withToken($intruderTokens->accessToken)
            ->deleteJson("/api/auth/sessions/{$sessionId}")
            ->assertNotFound();
    }
}
