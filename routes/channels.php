<?php

use App\Broadcasting\ClientChannelAuthorizer;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Placeholder policy: any authenticated ("id-api") user may listen.
// 'otp-deliveries' only ever carries a masked contact + purpose, never the
// code itself (see OtpCodeBroadcast) — but "any logged-in user" is still
// wider than it should be for a real ops channel. Tighten this (e.g. to a
// dedicated role once one exists) before pointing a real broadcast driver
// (BROADCAST_CONNECTION is currently "log") at this channel.
//
// $user is nullable (not just defensive): this same routes/channels.php now
// also serves POST /api/broadcasting/client-auth (see
// BroadcastingClientAuthController), which authenticates via client
// headers, not the "id-api" guard — a request for this channel through
// that route would resolve no user at all.
Broadcast::channel('otp-deliveries', function (?User $user) {
    return $user !== null;
});

/**
 * A downstream client's own private channel, reached only via
 * POST /api/broadcasting/client-auth (the "client" middleware, not
 * auth:id-api). See ClientChannelAuthorizer — a named class instead of an
 * inline closure so the authorization logic is directly unit-testable.
 */
Broadcast::channel('client.{clientId}', ClientChannelAuthorizer::class);
