<?php

namespace App\Http\Middleware;

use App\Auth\ClientSecret;
use App\Auth\CurrentClient;
use App\Exceptions\Auth\ClientAuthenticationFailedException;
use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the calling Gateway (not the end user) via X-Client-Id /
 * X-Client-Secret headers, and makes the resolved Client available for the
 * rest of the request through CurrentClient. Applied to the whole "auth"
 * route group, including register/login/otp, which have no other auth at
 * all — see routes/api.php.
 */
final readonly class AuthenticateClient
{
    public function __construct(private CurrentClient $currentClient) {}

    /**
     * @throws ClientAuthenticationFailedException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientId = $request->header('X-Client-Id');
        $secret = $request->header('X-Client-Secret');

        $client = is_string($clientId)
            ? Client::query()->where('client_id', $clientId)->where('is_active', true)->first()
            : null;

        if ($client === null || ! is_string($secret) || ! hash_equals($client->client_secret_hash, ClientSecret::hash($secret))) {
            throw new ClientAuthenticationFailedException;
        }

        $this->currentClient->set($client);

        return $next($request);
    }
}
