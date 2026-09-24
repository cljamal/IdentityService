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

    /**
     * The "log" driver's validAuthenticationResponse() is an empty no-op
     * (returns null), so this only proves our own authorization passes —
     * it says nothing about the response body/format a real Pusher/Reverb
     * driver would actually send back to the client.
     */
    public function test_a_client_is_authorized_for_its_own_private_channel(): void
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

    public function test_a_presence_channel_is_rejected(): void
    {
        $client = Client::factory()->create();

        // PusherBroadcaster::validAuthenticationResponse() branches on
        // str_starts_with($request->channel_name, 'private') — anything
        // else falls into its presence-channel branch, which calls
        // retrieveUser() and then ->getAuthIdentifier() on the result.
        // There's no end-user on this route, so that would be a null
        // method call (fatal error) if this ever reached
        // validAuthenticationResponse() — it must be rejected before that.
        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'presence-client.'.$client->client_id,
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_a_channel_without_the_private_prefix_is_rejected(): void
    {
        $client = Client::factory()->create();

        // Same crash risk as the presence case above, plus a public
        // channel has no legitimate reason to call this auth endpoint at
        // all in the real protocol.
        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->postJson('/api/broadcasting/client-auth', [
            'channel_name' => 'client.'.$client->client_id,
            'socket_id' => '1234.5678',
        ])->assertForbidden();
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
