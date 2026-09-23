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
 * building against the API. Not registered when APP_ENV=production,
 * see routes/api.php.
 */
class MeController extends Controller
{
    public function show(): JsonResponse
    {
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
