<?php

namespace Tests\Feature\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Auth\RefreshToken;
use App\Events\Ops\RefreshTokenReuseDetected;
use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use App\Repositories\EloquentAuthSessionRepository;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ActsAsClient;
use Tests\Fakes\RotationRaceLosingAuthSessionRepository;
use Tests\TestCase;

class SessionTrackingTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_login_records_a_session(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();

        IdApiGuard::current()->loginWithRefreshToken($user);

        $this->assertDatabaseCount('auth_sessions', 1);
        $this->assertDatabaseHas('auth_sessions', [
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
    }

    public function test_refresh_rotates_the_existing_session_instead_of_creating_a_new_one(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

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
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])->assertOk();

        // No grace window: a previous-generation token is always treated as
        // reused, immediately, even on the very next request — see
        // IdApiGuard::rejectReplayedRefreshToken().
        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'REFRESH_TOKEN_REUSED');

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
    }

    public function test_losing_a_concurrent_refresh_race_does_not_revoke_the_winners_session(): void
    {
        // Two requests refreshing the same token at once both read the
        // session before either writes: the winner's rotate() succeeds,
        // the loser's reports false (see EloquentAuthSessionRepository::
        // rotate()'s conditional UPDATE). That's an ordinary race, not a
        // replay of a stale token — it must not revoke the session the
        // winner just rotated into, nor raise a reuse alert.
        //
        // The bind() must happen before the guard is ever resolved:
        // AuthManager::guard() caches resolved guards for the app's
        // lifetime ($this->guards[$name] ??= ...), and IdApiGuard is
        // constructed with whatever AuthSessionRepositoryInterface was
        // bound at that moment. Binding afterwards — even before the
        // postJson() call below — would have no effect, since the
        // already-cached guard keeps its original (real) repository.
        $this->app->bind(
            AuthSessionRepositoryInterface::class,
            fn () => new RotationRaceLosingAuthSessionRepository(new EloquentAuthSessionRepository),
        );

        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        Event::fake([RefreshTokenReuseDetected::class]);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');

        Event::assertNotDispatched(RefreshTokenReuseDetected::class);

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($session->revoked_at);
    }

    public function test_refresh_rejects_an_unknown_token(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        IdApiGuard::current()->loginWithRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => 'not-a-real-token'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_refresh_rejects_a_missing_token_instead_of_returning_a_validation_error(): void
    {
        // An old client still on the previous Bearer-only refresh contract
        // sends no body at all — it must land on the same "log in again"
        // signal (401) as any other invalid token, not a 422 it has no
        // handling for.
        $this->postJson('/api/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_refresh_rejects_an_expired_token(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        AuthSession::query()->where('user_id', $user->id)->update([
            'refresh_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_refresh_rejects_a_token_belonging_to_a_revoked_session(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/logout')->assertOk();

        // Not REFRESH_TOKEN_REUSED — the session is simply gone, not being
        // actively replayed against.
        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_replaying_an_already_rotated_token_after_logout_is_invalid_not_reused(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $refreshed = $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken]);
        $refreshed->assertOk();

        $this->withToken($refreshed->json('data.access_token'))->postJson('/api/auth/logout')->assertOk();

        // $tokens->refreshToken is now "previous" (already rotated away)
        // on a session that's since been cleanly logged out — that's not
        // an active replay attack, so it must not come back as "reused".
        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');
    }

    public function test_the_pre_refresh_access_token_stops_working_after_a_refresh(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])->assertOk();

        // Still cryptographically valid and unexpired — only the fact that
        // its jti was rotated away should make it stop working.
        $this->withToken($tokens->accessToken)->getJson('/api/auth/sessions')->assertUnauthorized();
    }

    public function test_a_session_stays_listed_after_its_recorded_access_token_expiry_has_passed(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        // expires_at is just a bookkeeping copy of the access token's own
        // exp claim — it doesn't gate authentication (the token itself is
        // still cryptographically valid) and it must not gate the listing
        // either, since the refresh token backing this session is still
        // good for another 14 days.
        AuthSession::query()->where('user_id', $user->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($tokens->accessToken)
            ->getJson('/api/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_concurrent_rotation_of_the_same_refresh_token_only_lets_one_through(): void
    {
        /** @var AuthSessionRepositoryInterface $repository */
        $repository = app(AuthSessionRepositoryInterface::class);

        $user = User::factory()->for($this->defaultClient)->create();
        $repository->record($user, 'jti-1', now()->addHour(), 'hash-1', now()->addDays(14), null, null);

        // Both "requests" read the row before either one writes to it —
        // exactly what two concurrent refresh calls with the same token
        // would each see.
        $session = AuthSession::query()->where('jti', 'jti-1')->firstOrFail();

        $this->assertTrue($repository->rotate($session, 'jti-2', now()->addHour(), 'hash-2', now()->addDays(14), null, null));

        // $session in memory still holds the pre-rotation hash ("hash-1"),
        // so this is the "loser" of the race.
        $this->assertFalse($repository->rotate($session, 'jti-3', now()->addHour(), 'hash-3', now()->addDays(14), null, null));

        $this->assertSame('jti-2', AuthSession::query()->where('id', $session->id)->value('jti'));
    }

    public function test_logout_marks_the_session_revoked(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->withToken($tokens->accessToken)->postJson('/api/auth/logout')->assertOk();

        $session = AuthSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
    }

    public function test_logout_revokes_the_same_session_after_a_refresh_rotates_it_in_the_logout_event(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $guard = IdApiGuard::current();
        $tokens = $guard->loginWithRefreshToken($user);
        $payload = $guard->getPayload();
        $session = AuthSession::query()->where('jti', $payload->get('jti'))->firstOrFail();

        $otherTokens = $guard->loginWithRefreshToken($user);
        $otherSession = AuthSession::query()
            ->where('refresh_token_hash', RefreshToken::hash($otherTokens->refreshToken))
            ->firstOrFail();

        /** @var AuthSessionRepositoryInterface $sessions */
        $sessions = $this->app->make(AuthSessionRepositoryInterface::class);
        $rotationSucceeded = false;
        $concurrentAccessToken = '';
        $concurrentRefreshToken = null;
        $concurrentJti = '';

        Event::listen(Logout::class, function (Logout $event) use (
            &$rotationSucceeded,
            &$concurrentAccessToken,
            &$concurrentRefreshToken,
            &$concurrentJti,
            $guard,
            $session,
            $sessions,
            $user,
        ): void {
            $concurrentAccessToken = $guard->tokenById($user->getAuthIdentifier());
            $this->assertIsString($concurrentAccessToken);
            $guard->setToken($concurrentAccessToken);
            $newPayload = $guard->getPayload();
            $concurrentJti = (string) $newPayload->get('jti');
            $concurrentRefreshToken = RefreshToken::generate();

            $rotationSucceeded = $sessions->rotate(
                $session,
                $concurrentJti,
                Carbon::createFromTimestamp((int) $newPayload->get('exp')),
                $concurrentRefreshToken->hash,
                now()->addDays(14),
                null,
                null,
            );
        });

        $this->withToken($tokens->accessToken)->postJson('/api/auth/logout')->assertOk();

        $this->assertTrue($rotationSucceeded);
        $this->assertNotSame('', $concurrentAccessToken);
        $this->assertNotNull($concurrentRefreshToken);
        $this->assertNotNull($session->fresh()->revoked_at);
        $this->assertTrue($sessions->isRevoked($concurrentJti));
        $this->assertNull($sessions->findActiveByRefreshTokenHash($concurrentRefreshToken->hash));
        $this->assertSame($otherSession->id, $sessions->findActiveByRefreshTokenHash(
            RefreshToken::hash($otherTokens->refreshToken),
        )?->id);

        $this->withToken($concurrentAccessToken)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_refresh_rotation_is_rejected_when_logout_revokes_after_the_refresh_read(): void
    {
        $inner = new EloquentAuthSessionRepository;
        $this->app->bind(
            AuthSessionRepositoryInterface::class,
            fn () => new RotationRaceLosingAuthSessionRepository(
                $inner,
                function (AuthSession $session) use ($inner): void {
                    $inner->revokeById($session->id);
                },
            ),
        );

        $user = User::factory()->for($this->defaultClient)->create();
        $guard = IdApiGuard::current();
        $tokens = $guard->loginWithRefreshToken($user);
        $otherTokens = $guard->loginWithRefreshToken($user);
        $session = AuthSession::query()
            ->where('refresh_token_hash', RefreshToken::hash($tokens->refreshToken))
            ->firstOrFail();
        $otherSession = AuthSession::query()
            ->where('refresh_token_hash', RefreshToken::hash($otherTokens->refreshToken))
            ->firstOrFail();

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN')
            ->assertJsonMissingPath('data.access_token')
            ->assertJsonMissingPath('data.refresh_token');

        $revokedSession = $session->fresh();
        $this->assertNotNull($revokedSession);
        $this->assertNotNull($revokedSession->revoked_at);
        $this->assertSame($session->refresh_token_hash, $revokedSession->refresh_token_hash);
        $this->assertSame($otherSession->id, $inner->findActiveByRefreshTokenHash(
            RefreshToken::hash($otherTokens->refreshToken),
        )?->id);
        $this->assertSame(2, AuthSession::query()->where('user_id', $user->id)->count());
    }

    public function test_can_list_sessions_with_the_current_one_flagged(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $response = $this->withToken($tokens->accessToken)->getJson('/api/auth/sessions');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertTrue($response->json('data.0.is_current'));
    }

    public function test_revoking_a_session_immediately_invalidates_its_token(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);
        $sessionId = AuthSession::query()->where('user_id', $user->id)->value('id');

        $this->withToken($tokens->accessToken)->deleteJson("/api/auth/sessions/{$sessionId}")->assertOk();

        // The token itself is still cryptographically valid and unexpired —
        // only our own revocation record makes it stop working.
        $this->withToken($tokens->accessToken)->getJson('/api/auth/sessions')->assertUnauthorized();
    }

    public function test_revoking_another_users_session_looks_like_not_found(): void
    {
        $owner = User::factory()->for($this->defaultClient)->create();
        IdApiGuard::current()->loginWithRefreshToken($owner);
        $sessionId = AuthSession::query()->where('user_id', $owner->id)->value('id');

        $intruder = User::factory()->for($this->defaultClient)->create();
        $intruderTokens = IdApiGuard::current()->loginWithRefreshToken($intruder);

        $this->withToken($intruderTokens->accessToken)
            ->deleteJson("/api/auth/sessions/{$sessionId}")
            ->assertNotFound();
    }
}
