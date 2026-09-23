<?php

namespace App\Auth\Guards;

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
use PHPOpenSourceSaver\JWTAuth\Token;

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
     * (login()/refresh()/getTTL() aren't on it), and this is the one
     * place that tells it what we actually registered.
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
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        // Same cache parent relies on — also skips re-hitting isRevoked()
        // on every later ->user() call in the same request (most actions
        // here call it again after the auth:id-api middleware already did).
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
     * As parent, but also records the freshly issued token as a session.
     *
     * @return string
     */
    public function login(JWTSubject $user)
    {
        $token = parent::login($user);

        $payload = $this->getPayload();

        /** @var User $user */
        $this->sessions->record(
            $user,
            $payload->get('jti'),
            Carbon::createFromTimestamp((int) $payload->get('exp')),
            $this->request->ip(),
            $this->request->userAgent(),
        );

        return $token;
    }

    /**
     * As parent, but rotates the old token's session onto the new jti
     * instead of leaving a stale row behind (a refresh is a continuation
     * of the same session, not a new login).
     *
     * @param  bool  $forceForever
     * @param  bool  $resetClaims
     * @return string
     */
    public function refresh($forceForever = false, $resetClaims = false)
    {
        $oldJti = $this->getPayload()->get('jti');

        // buildRefreshClaims() in the underlying library does NOT call
        // getJWTCustomClaims() again — it only carries over claims listed
        // in config('jwt.persistent_claims'), which is empty here. Without
        // this, "role" would silently vanish from the token after the
        // very first refresh instead of being re-derived from current DB
        // state (e.g. picking up a role change since the last login).
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser) {
            $this->claims($currentUser->getJWTCustomClaims());
        }

        $newToken = parent::refresh($forceForever, $resetClaims);

        $payload = $this->jwt->manager()->decode(new Token($newToken));
        $expiresAt = Carbon::createFromTimestamp((int) $payload->get('exp'));

        $rotated = $this->sessions->rotate($oldJti, $payload->get('jti'), $expiresAt);

        if (! $rotated && $currentUser) {
            $this->sessions->record(
                $currentUser,
                $payload->get('jti'),
                $expiresAt,
                $this->request->ip(),
                $this->request->userAgent(),
            );
        }

        return $newToken;
    }

    /**
     * As parent, but also marks the session revoked so it drops off the
     * "active sessions" list immediately rather than lingering until its
     * natural expiry.
     *
     * @param  bool  $forceForever
     * @return void
     */
    public function logout($forceForever = false)
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
