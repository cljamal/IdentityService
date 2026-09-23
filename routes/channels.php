<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Placeholder policy: any authenticated ("id-api") user may listen.
// 'otp-deliveries' only ever carries a masked contact + purpose, never the
// code itself (see OtpCodeBroadcast) — but "any logged-in user" is still
// wider than it should be for a real ops channel. Tighten this (e.g. to a
// dedicated role once one exists) before pointing a real broadcast driver
// (BROADCAST_CONNECTION is currently "log") at this channel.
Broadcast::channel('otp-deliveries', function (User $user) {
    return true;
});
