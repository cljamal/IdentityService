<?php

namespace Tests\Feature\Events\Ops;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Enums\SessionRevocationReason;
use App\Events\Ops\AccountDeleted;
use App\Events\Ops\RefreshTokenReuseDetected;
use App\Events\Ops\ServiceDisabled;
use App\Events\Ops\ServiceEnabled;
use App\Events\Ops\TestBroadcast;
use App\Events\Ops\UserPhoneChanged;
use App\Events\Ops\UserRegistered;
use App\Events\Ops\UserSessionRevoked;
use App\Models\Client;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pure shape assertions — broadcastOn()/broadcastAs()/broadcastWith() — with
 * no transport involved. Uses real, persisted models (not in-memory ones)
 * because every event's targetClient() resolves $user->client, a real
 * Eloquent relation.
 */
class OpsBroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_session_revoked_shape(): void
    {
        $user = User::factory()->create();
        $event = new UserSessionRevoked($user, SessionRevocationReason::Logout);

        $this->assertChannel($event, $user->client);
        $this->assertSame('user.session_revoked', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($user->uuid, $payload['user_uuid']);
        $this->assertSame($user->client->client_id, $payload['client_id']);
        $this->assertSame('logout', $payload['reason']);
        $this->assertArrayHasKey('revoked_at', $payload);
    }

    public function test_refresh_token_reuse_detected_shape(): void
    {
        $user = User::factory()->create();
        $event = new RefreshTokenReuseDetected($user, '203.0.113.1', 'TestAgent/1.0');

        $this->assertChannel($event, $user->client);
        $this->assertSame('user.refresh_token_reuse_detected', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($user->uuid, $payload['user_uuid']);
        $this->assertSame('203.0.113.1', $payload['ip']);
        $this->assertSame('TestAgent/1.0', $payload['user_agent']);
        $this->assertArrayHasKey('detected_at', $payload);
    }

    public function test_user_registered_shape(): void
    {
        $user = User::factory()->create();
        $event = new UserRegistered($user, AuthProviderName::EmailPassword);

        $this->assertChannel($event, $user->client);
        $this->assertSame('user.registered', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($user->uuid, $payload['user_uuid']);
        $this->assertSame('email-password', $payload['provider']);
        $this->assertArrayHasKey('registered_at', $payload);
    }

    public function test_user_phone_changed_shape(): void
    {
        $user = User::factory()->create();
        $event = new UserPhoneChanged($user, '998900000000', '998911111111');

        $this->assertChannel($event, $user->client);
        $this->assertSame('user.phone_changed', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame('998900000000', $payload['old_phone']);
        $this->assertSame('998911111111', $payload['new_phone']);
    }

    public function test_account_deleted_shape(): void
    {
        $user = User::factory()->create();
        $event = new AccountDeleted($user);

        $this->assertChannel($event, $user->client);
        $this->assertSame('user.account_deleted', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame($user->uuid, $payload['user_uuid']);
        $this->assertArrayHasKey('deleted_at', $payload);
    }

    public function test_service_disabled_shape(): void
    {
        $client = Client::factory()->create();
        $event = new ServiceDisabled($client);

        $this->assertChannel($event, $client);
        $this->assertSame('service.disabled', $event->broadcastAs());
        $this->assertSame($client->client_id, $event->broadcastWith()['client_id']);
    }

    public function test_service_enabled_shape(): void
    {
        $client = Client::factory()->create();
        $event = new ServiceEnabled($client);

        $this->assertChannel($event, $client);
        $this->assertSame('service.enabled', $event->broadcastAs());
        $this->assertSame($client->client_id, $event->broadcastWith()['client_id']);
    }

    public function test_test_broadcast_shape(): void
    {
        $client = Client::factory()->create();
        $event = new TestBroadcast($client, 'hello');

        $this->assertChannel($event, $client);
        $this->assertSame('ops.test', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame('hello', $payload['message']);
        $this->assertSame($client->client_id, $payload['client_id']);
    }

    private function assertChannel(object $event, Client $client): void
    {
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-client.'.$client->client_id, (string) $channels[0]);
    }
}
