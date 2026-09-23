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
        $token = IdApiGuard::current()->login($user);

        $originalJti = AuthSession::query()->where('user_id', $user->id)->value('jti');

        $this->withToken($token)->postJson('/api/auth/refresh')->assertOk();

        $this->assertDatabaseCount('auth_sessions', 1);
        $this->assertNotSame(
            $originalJti,
            AuthSession::query()->where('user_id', $user->id)->value('jti'),
        );
    }

    public function test_logout_marks_the_session_revoked(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
    }

    public function test_can_list_sessions_with_the_current_one_flagged(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);

        $response = $this->withToken($token)->getJson('/api/auth/sessions');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertTrue($response->json('data.0.is_current'));
    }

    public function test_revoking_a_session_immediately_invalidates_its_token(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);
        $sessionId = AuthSession::query()->where('user_id', $user->id)->value('id');

        $this->withToken($token)->deleteJson("/api/auth/sessions/{$sessionId}")->assertOk();

        // The token itself is still cryptographically valid and unexpired —
        // only our own revocation record makes it stop working.
        $this->withToken($token)->getJson('/api/auth/sessions')->assertUnauthorized();
    }

    public function test_revoking_another_users_session_looks_like_not_found(): void
    {
        $owner = User::factory()->create();
        IdApiGuard::current()->login($owner);
        $sessionId = AuthSession::query()->where('user_id', $owner->id)->value('id');

        $intruder = User::factory()->create();
        $intruderToken = IdApiGuard::current()->login($intruder);

        $this->withToken($intruderToken)
            ->deleteJson("/api/auth/sessions/{$sessionId}")
            ->assertNotFound();
    }
}
