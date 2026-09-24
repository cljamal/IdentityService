<?php

namespace App\Http\Controllers;

use App\Broadcasting\ClientChannelAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The client-secret-authenticated counterpart to the framework's own
 * POST /api/broadcasting/auth (registered via withBroadcasting() in
 * bootstrap/app.php, behind auth:id-api). BroadcastManager::routes()
 * hardcodes that URI, so it can't be registered a second time with
 * different middleware — this is a separate route.
 *
 * Deliberately does NOT call Broadcast::auth($request) — every concrete
 * driver that matters here (PusherBroadcaster, which Reverb also uses, and
 * AblyBroadcaster) overrides auth() to reject any "private-"/"presence-"
 * channel outright when $request->user() is null, before it ever reaches
 * our registered channel callback. That check is about end-user guards;
 * there is no end-user on this route at all, by design (the caller
 * authenticates as a client, via AuthenticateClient). Running the
 * authorization ourselves and calling validAuthenticationResponse()
 * directly skips that driver-specific gate while still producing whatever
 * wire-format response the configured driver actually expects.
 *
 * Only the "private-" prefix is accepted, and it's checked before anything
 * else runs. PusherBroadcaster::validAuthenticationResponse() branches on
 * str_starts_with($request->channel_name, 'private') — anything else
 * (a "presence-" channel, or a bare public one with no prefix at all)
 * falls into its presence-channel branch, which calls
 * $user->getAuthIdentifier() on whatever retrieveUser() returns. There is
 * no end-user on this route, so that's always null, and PHP fatals calling
 * a method on it. A public channel needs no auth call at all in the real
 * protocol, and this client-channel feature has no presence use case, so
 * both are rejected outright rather than routed into that branch.
 */
final class BroadcastingClientAuthController extends Controller
{
    private const string PRIVATE_PREFIX = 'private-';

    public function __invoke(Request $request, ClientChannelAuthorizer $authorizer): mixed
    {
        $channelName = (string) $request->channel_name;

        if (! str_starts_with($channelName, self::PRIVATE_PREFIX)) {
            throw new AccessDeniedHttpException;
        }

        $channelName = substr($channelName, strlen(self::PRIVATE_PREFIX));

        if (! preg_match('/^client\.(?<clientId>.+)$/', $channelName, $matches)) {
            throw new AccessDeniedHttpException;
        }

        if (! $authorizer->join(null, $matches['clientId'])) {
            throw new AccessDeniedHttpException;
        }

        return Broadcast::validAuthenticationResponse($request, true);
    }
}
