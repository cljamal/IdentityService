<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResetClientSecretAction;
use App\Auth\ClientSecret;
use App\Models\Client;
use Database\Factories\ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClientSecretRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_replaces_the_hash_and_returns_the_new_secret_without_changing_other_clients(): void
    {
        $client = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $originalId = $client->client_id;

        $secret = ResetClientSecretAction::run($client);

        $client->refresh();
        $this->assertSame($originalId, $client->client_id);
        $this->assertSame(ClientSecret::hash($secret->plainText), $client->client_secret_hash);
        $this->assertNotSame(ClientFactory::PLAIN_SECRET, $secret->plainText);
        $this->assertNotSame(ClientFactory::PLAIN_SECRET, $client->client_secret_hash);
        $this->assertSame(ClientSecret::hash(ClientFactory::PLAIN_SECRET), $otherClient->fresh()->client_secret_hash);
        $this->assertArrayNotHasKey('client_secret_hash', $client->toArray());
    }

    public function test_old_credentials_are_rejected_and_new_credentials_pass_client_authentication(): void
    {
        $client = Client::factory()->create();

        $secret = ResetClientSecretAction::run($client);

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => ClientFactory::PLAIN_SECRET,
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');

        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => $secret->plainText,
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonMissingPath('code');
    }

    public function test_rotation_does_not_reactivate_an_inactive_client(): void
    {
        $client = Client::factory()->inactive()->create();

        $secret = ResetClientSecretAction::run($client);

        $this->assertFalse($client->fresh()->is_active);
        $this->withHeaders([
            'X-Client-Id' => $client->client_id,
            'X-Client-Secret' => $secret->plainText,
        ])->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'CLIENT_AUTHENTICATION_FAILED');
    }

    public function test_command_prints_a_secret_matching_the_stored_hash_for_the_public_client_id(): void
    {
        $client = Client::factory()->create();
        $this->withoutMockingConsoleOutput();

        $exitCode = $this->artisan('client:reset-secret', ['client' => $client->client_id]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString($client->client_id, $output);
        $this->assertSame(1, preg_match('/X-Client-Secret:\s+([A-Za-z0-9]{48})/', $output, $matches));
        $this->assertSame(ClientSecret::hash($matches[1]), $client->fresh()->client_secret_hash);
    }

    public function test_command_rejects_an_unknown_client_without_changing_existing_credentials(): void
    {
        $client = Client::factory()->create();

        $this->artisan('client:reset-secret', ['client' => 'unknown-client'])
            ->expectsOutput('Клиент с указанным client_id не найден.')
            ->assertFailed();

        $this->assertSame(ClientSecret::hash(ClientFactory::PLAIN_SECRET), $client->fresh()->client_secret_hash);
    }
}
