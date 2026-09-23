<?php

namespace App\Http\Controllers;

use App\Auth\Guards\IdApiGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Dev-only introspection: dumps the decoded claims of the JWT used for
 * this request. A JWT's payload is base64, not encrypted, so this reveals
 * nothing a caller couldn't already read themselves via jwt.io or
 * atob(token.split('.')[1]) — it just saves that copy-paste while
 * building against the API. Refuses to run when APP_ENV=production —
 * checked here, not by conditionally registering the route in
 * routes/api.php, since `route:cache` would freeze that check at
 * build time instead of evaluating it per request.
 */
class MeController extends Controller
{
    public function show(): JsonResponse
    {
        abort_if(app()->environment('production'), 404);

        $guard = IdApiGuard::current();
        $payload = $guard->getPayload();
        $user = $guard->user();

        $now = now()->timestamp;
        $issuedAt = (int) $payload->get('iat');
        $expiresAt = (int) $payload->get('exp');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'claims' => $payload->toArray(),
            'readable' => [
                'issued_at' => Carbon::createFromTimestamp($issuedAt)->toIso8601String(),
                'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
                'age_seconds' => $now - $issuedAt,
                'expires_in_seconds' => $expiresAt - $now,
            ],
        ]);
    }
}
