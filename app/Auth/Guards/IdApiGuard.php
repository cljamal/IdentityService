<?php

namespace App\Auth\Guards;

use App\Auth\RefreshToken;
use App\Auth\TokenPair;
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
     * (login()/refreshUsingToken()/getTTL() aren't on it), and this is the
     * one place that tells it what we actually registered.
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
     * cryptographically valid and unexpired.
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

        if ($this->sessions->isRevoked($this->getPayload()->get('jti'))) {
            $this->user = null;

            return null;
        }

        return $user;
    }

    /**
     * As parent, but also issues an opaque refresh token and records both
     * as a session.
     */
    public function login(JWTSubject $user): TokenPair
    {
        $accessToken = parent::login($user);

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
            $reused = $this->sessions->findByPreviousRefreshTokenHash($hash);

            if ($reused !== null) {
                $this->sessions->revoke($reused);

                throw new RefreshTokenReusedException;
            }

            throw new InvalidRefreshTokenException;
        }

        /** @var User|null $user */
        $user = $session->user;

        if ($user === null) {
            throw new InvalidRefreshTokenException;
        }

        $accessToken = $this->jwt->fromUser($user);
        $this->setToken($accessToken)->setUser($user);

        $payload = $this->getPayload();
        $newRefreshToken = RefreshToken::generate();

        $this->sessions->rotate(
            $session,
            $payload->get('jti'),
            Carbon::createFromTimestamp((int) $payload->get('exp')),
            $newRefreshToken->hash,
            $this->refreshTokenExpiresAt(),
        );

        return new TokenPair($accessToken, $newRefreshToken->plainText);
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
