<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateClient;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Tests\TestCase;

/**
 * Regression test for a real ordering bug: "client" was registered only as
 * a middleware alias, never added to the priority list bootstrap/app.php's
 * withMiddleware() builds. Illuminate\Routing\SortedMiddleware always moves
 * anything implementing AuthenticatesRequests (i.e. "auth:id-api") ahead of
 * middleware that isn't in the priority list at all, on any route that
 * combines both (me, sessions, logout, password/change, ...) — meaning
 * IdApiGuard::user() ran, resolved and cached the user, before
 * AuthenticateClient ever populated CurrentClient. Its cross-client check
 * (`if ($client !== null && ...)`) silently no-ops when $client is null,
 * so an access token minted under Client A would authenticate through
 * Client B's credentials on every one of those routes.
 *
 * This checks the actual runtime priority list the router ends up with
 * (after bootstrap/app.php's prependToPriorityList() call), rather than
 * relying solely on an end-to-end request to prove the ordering — the
 * fix is entirely about *configuration*, so this is the most direct test
 * of it.
 */
class MiddlewarePriorityTest extends TestCase
{
    public function test_the_client_middleware_is_prioritized_ahead_of_the_auth_middleware(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $priority = app('router')->middlewarePriority;

        $clientIndex = array_search(AuthenticateClient::class, $priority, true);
        $authIndex = array_search(AuthenticatesRequests::class, $priority, true);

        $this->assertNotFalse($clientIndex, 'AuthenticateClient is missing from the middleware priority list.');
        $this->assertNotFalse($authIndex, 'AuthenticatesRequests is missing from the middleware priority list.');
        $this->assertLessThan(
            $authIndex,
            $clientIndex,
            '"client" must be sorted ahead of "auth:id-api" so CurrentClient is populated before IdApiGuard::user() runs.',
        );
    }
}
