<?php

namespace Tests\Feature\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Models\AuthSession;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsClient;
use Tests\TestCase;

class ClientDeactivationTest extends TestCase
{
    use RefreshDatabase, ActsAsClient;

    /**
     * Not load-bearing for security (AuthenticateClient and IdApiGuard::user()
     * already block a deactivated client's sessions live), but revoked_at
     * should reflect reality — for the audit trail and so a session doesn't
     * sit there looking active in "my sessions" forever for no reason.
     */
    public function test_deactivating_a_client_revokes_every_session_of_every_user_under_it(): void
    {
        $userA = User::factory()->for($this->defaultClient)->create();
        $userB = User::factory()->for($this->defaultClient)->create();

        IdApiGuard::current()->loginWithRefreshToken($userA);
        IdApiGuard::current()->loginWithRefreshToken($userB);

        $this->defaultClient->update(['is_active' => false]);

        $this->assertSame(
            0,
            AuthSession::query()
                ->whereIn('user_id', [$userA->id, $userB->id])
                ->whereNull('revoked_at')
                ->count(),
        );
    }

    public function test_reactivating_a_client_does_not_revoke_a_session_created_afterwards(): void
    {
        $this->defaultClient->update(['is_active' => false]);
        $this->defaultClient->update(['is_active' => true]);

        // Only the false-transition revokes — flipping back to true must
        // not itself act like a fresh "log everyone out", or a client could
        // never be un-paused without nuking sessions created afterwards.
        $user = User::factory()->for($this->defaultClient)->create();
        IdApiGuard::current()->loginWithRefreshToken($user);

        $this->assertSame(
            1,
            AuthSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->count(),
        );
    }

    public function test_a_deactivated_clients_gateway_is_rejected_at_the_door(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->defaultClient->update(['is_active' => false]);

        $this->withToken($tokens->accessToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');

        $this->postJson('/api/auth/refresh', ['refresh_token' => $tokens->refreshToken])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    /**
     * Regression test: IdApiGuard::user() used to check client status only
     * when a client had been resolved for the request, which meant a route
     * that never runs the "client" middleware (like /api/broadcasting/auth
     * — see bootstrap/app.php) would keep accepting a deactivated client's
     * tokens indefinitely. It now reads $user->client->is_active directly,
     * so this is enforced regardless of which route asks.
     *
     * Exercised at the guard level, not via HTTP: every route this service
     * actually exposes under /api/auth/* already requires the "client"
     * middleware, so there's no real endpoint to reproduce the gap through.
     * A fresh guard instance is built directly (bypassing the Auth
     * manager's cached instance) so the user() call below isn't just
     * returning the $user already cached on the guard by login() above.
     */
    public function test_an_access_token_is_rejected_once_its_client_is_deactivated_even_without_the_client_middleware(): void
    {
        $user = User::factory()->for($this->defaultClient)->create();
        $tokens = IdApiGuard::current()->loginWithRefreshToken($user);

        $this->defaultClient->update(['is_active' => false]);

        $guard = IdApiGuard::resolve($this->app, 'id-api', config('auth.guards.id-api'));
        $guard->setToken($tokens->accessToken);

        $this->assertNull($guard->user());
    }
}
