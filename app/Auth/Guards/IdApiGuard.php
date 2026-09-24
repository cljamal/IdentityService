<?php

namespace App\Auth\Guards;

use App\Auth\CurrentClient;
use App\Auth\RefreshToken;
use App\Auth\TokenPair;
use App\Events\Ops\RefreshTokenReuseDetected;
use App\Exceptions\Auth\InvalidRefreshTokenException;
use App\Exceptions\Auth\RefreshTokenReusedException;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWT;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Our own guard for issuing/validating identity tokens, kept as a real
 * subclass (not just the vendor "jwt" driver renamed) so that any future
 * customization (extra claims, logging, ...) has a home we control,
 * instead of every call site depending on php-open-source-saver's guard
 * directly. Registered as the "id-api" driver via Auth::extend() in
 * AppServiceProvider, which just points at resolve() below.
 *
 * Also the home for session tracking: every issued token is recorded by
 * its "jti" claim (see AuthSessionRepositoryInterface) so a user can list
 * and revoke their active sessions independently of the library's own
 * blacklist, which only ever targets "the token this request is using".
 */
final class IdApiGuard extends JWTGuard
{
    public function __construct(
        JWT $jwt,
        UserProvider $provider,
        Request $request,
        Dispatcher $events,
        private readonly AuthSessionRepositoryInterface $sessions,
        private readonly CurrentClient $currentClient,
    ) {
        parent::__construct($jwt, $provider, $request, $events);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws BindingResolutionException
     */
    public static function resolve(Application $app, string $name, array $config): self
    {
        $guard = new self(
            $app['tymon.jwt'],
            Auth::createUserProvider($config['provider']),
            $app['request'],
            $app['events'],
            $app->make(AuthSessionRepositoryInterface::class),
            $app->make(CurrentClient::class),
        );

        $guard->setTTL(
            Arr::get($config, 'ttl', $app->make('config')->get('jwt.ttl')),
        );

        $app->refresh('request', $guard, 'setRequest');

        return $guard;
    }

    /**
     * Typed accessor for the currently resolved "id-api" guard — PHPStan
     * only knows Auth::guard() as the generic StatefulGuard contract
     * (loginWithRefreshToken()/refreshUsingToken()/getTTL() aren't on it),
     * and this is the one place that tells it what we actually registered.
     */
    public static function current(): self
    {
        /** @var self $guard */
        $guard = Auth::guard('id-api');

        return $guard;
    }

    /**
     * As parent, but a token whose jti was explicitly revoked (via the
     * sessions endpoint) is rejected even though it's still
     * cryptographically valid and unexpired, same for a token whose owner's
     * client has since been deactivated, and same for a token minted for a
     * different client than the one authenticated on this request — a
     * token leaked from Client A's Gateway must not work when replayed
     * through Client B's.
     *
     * The deactivation check reads $user->client->is_active fresh from the
     * DB rather than trusting the JWT's client_id claim, and runs
     * regardless of whether a client was resolved for this request at all
     * — this guard is also used on /api/broadcasting/auth (see
     * bootstrap/app.php), which sits outside the /api/auth prefix the
     * "client" middleware wraps, so a deactivated client's already-issued
     * tokens must not keep working there just because that route never
     * runs AuthenticateClient. The cross-client claim check further below
     * still only applies where a client *was* resolved.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $user = parent::user();

        if ($user === null) {
            return null;
        }

        $payload = $this->getPayload();

        if ($this->sessions->isRevoked($payload->get('jti'))) {
            $this->user = null;

            return null;
        }

        /** @var User $user */
        if (! $user->client->is_active) {
            $this->user = null;

            return null;
        }

        $client = $this->currentClient->resolved();

        if ($client !== null && $payload->get('client_id') !== $client->client_id) {
            $this->user = null;

            return null;
        }

        return $user;
    }

    /**
     * The login flow used everywhere in this app: mints an access token via
     * the inherited login(), pairs it with a fresh opaque refresh token,
     * and records both as a session. Deliberately not an override of
     * login() itself — JWTGuard fixes its return type at `string`, and
     * returning a TokenPair from it would violate that contract for any
     * caller still going through the plain Guard/StatefulGuard interface.
     */
    public function loginWithRefreshToken(JWTSubject $user): TokenPair
    {
        $accessToken = $this->login($user);

        $payload = $this->getPayload();
        $refreshToken = RefreshToken::generate();

        /** @var User $user */
        $this->sessions->record(
            $user,
            $payload->get('jti'),
            Carbon::createFromTimestamp((int) $payload->get('exp')),
            $refreshToken->hash,
            $this->refreshTokenExpiresAt(),
            $this->request->ip(),
            $this->request->userAgent(),
        );

        return new TokenPair($accessToken, $refreshToken->plainText);
    }

    /**
     * Exchange a refresh token for a new access/refresh pair, rotating the
     * same underlying session rather than creating a new one — a refresh
     * is a continuation of the same session, not a new login.
     *
     * A token already rotated away and presented again is treated as
     * stolen: the whole session is revoked instead of just being rejected.
     *
     * @throws InvalidRefreshTokenException
     * @throws RefreshTokenReusedException
     */
    public function refreshUsingToken(string $refreshToken): TokenPair
    {
        $hash = RefreshToken::hash($refreshToken);

        $session = $this->sessions->findActiveByRefreshTokenHash($hash);

        if ($session === null) {
            $this->rejectReplayedRefreshToken($hash);
        }

        /** @var User|null $user */
        $user = $session->user;

        if ($user === null) {
            throw new InvalidRefreshTokenException;
        }

        // Same code as "unknown token" on purpose — distinguishing "wrong
        // client" from "doesn't exist" would let a Gateway probe whether a
        // session exists under some other client's credentials.
        if ($user->client_id !== $this->currentClient->get()->id) {
            throw new InvalidRefreshTokenException;
        }

        $accessToken = $this->jwt->fromUser($user);
        $this->setToken($accessToken)->setUser($user);

        $payload = $this->getPayload();
        $newRefreshToken = RefreshToken::generate();

        $rotated = $this->sessions->rotate(
            $session,
            $payload->get('jti'),
            Carbon::createFromTimestamp((int) $payload->get('exp')),
            $newRefreshToken->hash,
            $this->refreshTokenExpiresAt(),
            $this->request->ip(),
            $this->request->userAgent(),
        );

        if (! $rotated) {
            // Someone else rotated this exact token first between our
            // lookup above and this write — a concurrent refresh, either
            // a legitimate retry or a live race with whoever else has
            // this token. Whoever loses the race is treated exactly like
            // a replay: we can't tell the two apart from here.
            $this->rejectReplayedRefreshToken($hash);
        }

        return new TokenPair($accessToken, $newRefreshToken->plainText);
    }

    /**
     * A grace window that tolerated a replayed-but-recent previous token was
     * tried here and removed: the presenting caller got 401 either way (no
     * new pair — a genuine retry never actually recovered), so the only
     * effect was suppressing REVOKED detection for up to N seconds after
     * every rotation, exactly the window an actual thief would use. Erring
     * toward detection over convenience for an auth service.
     *
     * @throws InvalidRefreshTokenException
     * @throws RefreshTokenReusedException
     */
    private function rejectReplayedRefreshToken(string $hash): never
    {
        $reused = $this->sessions->findByPreviousRefreshTokenHash($hash);

        if ($reused === null) {
            throw new InvalidRefreshTokenException;
        }

        $this->sessions->revoke($reused);

        /** @var User|null $user */
        $user = $reused->user;

        if ($user !== null) {
            RefreshTokenReuseDetected::dispatch($user, $this->request->ip(), $this->request->userAgent());
        }

        throw new RefreshTokenReusedException;
    }

    private function refreshTokenExpiresAt(): Carbon
    {
        return now()->addMinutes((int) config('identity.refresh_token_ttl'));
    }

    /**
     * As parent, but also marks the session revoked so it drops off the
     * "active sessions" list immediately rather than lingering until its
     * natural expiry.
     *
     * @param  bool  $forceForever
     */
    public function logout($forceForever = false): void
    {
        $jti = null;

        try {
            $jti = $this->getPayload()->get('jti');
        } catch (JWTException) {
        }

        parent::logout($forceForever);

        if ($jti !== null) {
            $this->sessions->revokeByJti($jti);
        }
    }
}
