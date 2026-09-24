<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Client;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Confirms POST /api/broadcasting/client-auth actually runs the "client"
 * middleware (not auth:id-api, which the framework's own /api/broadcasting/
 * auth uses), and that it authorizes correctly WITHOUT going through
 * Broadcast::auth() — see BroadcastingClientAuthController's docblock: every
 * real driver that matters here (PusherBroadcaster, which Reverb also uses,
 * and AblyBroadcaster) rejects any private/presence channel outright when
 * $request->user() is null, which it always is on this route. Calling
 * Broadcast::validAuthenticationResponse() directly, after authorizing
 * ourselves, skips that driver-specific gate while still working
 * regardless of which driver ends up configured.
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

    public function test_a_client_can_authenticate_for_its_own_channel(): void
    {
        $client = Client::factory()->create();

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'private-client.'.$client->client_id,
            'socket_id' => '1234.5678',
        ])->assertSuccessful();
    }

    public function test_a_client_cannot_authenticate_for_another_clients_channel(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'private-client.'.$otherClient->client_id,
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_an_unrelated_channel_name_is_rejected(): void
    {
        $client = Client::factory()->create();

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'private-otp-deliveries',
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }
}
