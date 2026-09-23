<?php

namespace App\Events\Notifications;

use App\Notifications\Otp\OtpDestination;
use App\Notifications\Otp\OtpPurpose;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The "WS" half of the Main -> WS + Action fan-out (see RouteOtpCodeDelivery).
 * Today BROADCAST_CONNECTION=log means this just logs like everything else,
 * but the shape is real: once a real driver (Reverb/Pusher) is configured,
 * this starts actually reaching whoever is authorized on 'otp-deliveries'
 * (see routes/channels.php) with zero code changes here.
 *
 * Deliberately does NOT carry the raw code — 'otp-deliveries' is meant as
 * an ops/monitoring channel (see routes/channels.php), not a delivery
 * channel to the end user, and its authorization isn't scoped per-user
 * (can't be, for anonymous pre-auth flows like login/registration). Once
 * there's a real per-destination private channel with real authorization,
 * broadcasting the code itself becomes a deliberate, separate decision.
 */
final class OtpCodeBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly OtpDestination $destination,
        public readonly OtpPurpose $purpose,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('otp-deliveries')];
    }

    public function broadcastAs(): string
    {
        return 'otp.issued';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'channel' => $this->destination->channel->value,
            'purpose' => $this->purpose->value,
            'contact' => $this->maskedContact(),
            'issued_at' => now()->toIso8601String(),
        ];
    }

    private function maskedContact(): string
    {
        $contact = $this->destination->contact;

        return strlen($contact) <= 4
            ? $contact
            : str_repeat('*', strlen($contact) - 4).substr($contact, -4);
    }
}
