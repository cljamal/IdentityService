<?php

namespace Tests\Feature\Broadcasting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms POST /api/broadcasting/client-auth actually runs the "client"
 * middleware (not auth:id-api, which the framework's own /api/broadcasting/
 * auth uses) — i.e. that the route wiring in routes/api.php is correct.
 * The channel authorization logic itself is covered directly and more
 * robustly in ClientChannelAuthorizerTest, without needing to exercise
 * Laravel's broadcasting transport internals (BroadcastManager resolves
 * whatever driver was the default when routes/channels.php first loaded,
 * at boot — not whatever a test swaps in afterwards).
 */
class BroadcastingClientAuthRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_client_credentials_are_rejected(): void
    {
        $this->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'private-client.whatever',
            'socket_id' => '1234.5678',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }
}
