<?php

namespace Tests\Feature\Broadcasting;

use App\Auth\CurrentClient;
use App\Broadcasting\ClientChannelAuthorizer;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientChannelAuthorizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizes_a_client_for_its_own_channel(): void
    {
        $client = Client::factory()->create();
        app(CurrentClient::class)->set($client);

        $authorizer = new ClientChannelAuthorizer(app(CurrentClient::class));

        $this->assertTrue($authorizer->join(null, $client->client_id));
    }

    public function test_does_not_authorize_a_client_for_another_clients_channel(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();
        app(CurrentClient::class)->set($clientA);

        $authorizer = new ClientChannelAuthorizer(app(CurrentClient::class));

        $this->assertFalse($authorizer->join(null, $clientB->client_id));
    }

    public function test_does_not_authorize_when_no_client_is_resolved(): void
    {
        $client = Client::factory()->create();

        $authorizer = new ClientChannelAuthorizer(app(CurrentClient::class));

        $this->assertFalse($authorizer->join(null, $client->client_id));
    }
}
