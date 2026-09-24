<?php

namespace App\Events\Ops;

use App\Models\Client;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Every event a client's own Gateway can subscribe to on its private
 * `client.{clientId}` channel (see routes/channels.php) — a third category
 * next to App\Events\Auth (internal facts) and App\Events\Notifications
 * (end-user delivery): signals broadcast outward to a downstream client.
 *
 * ShouldBroadcastNow means this broadcasts synchronously, inline, wherever
 * it's dispatched — several call sites (RegisterAction, PhoneOtpStrategy
 * ::authenticate()) dispatch it after the user is already created/logged in
 * and the OTP code already burned, with no way to roll that back. ShouldRescue
 * tells BroadcastManager::queue() to wrap the actual broadcast() call in
 * rescue() (report + swallow) instead of letting a transport failure bubble
 * up as a 500 on an auth flow that has already otherwise succeeded.
 *
 * Not a readonly class — InteractsWithSockets declares a mutable, untyped
 * `public $socket` property, same reason OtpCodeBroadcast isn't one either.
 */
abstract class OpsBroadcastEvent implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    abstract protected function targetClient(): Client;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('client.'.$this->targetClient()->client_id)];
    }
}
