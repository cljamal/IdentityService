<?php

namespace App\Auth\Guards;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * Our own guard for issuing/validating identity tokens, kept as a real
 * subclass (not just the vendor "jwt" driver renamed) so that any future
 * customization (extra claims, logging, ...) has a home we control,
 * instead of every call site depending on php-open-source-saver's guard
 * directly. Registered as the "id-api" driver via Auth::extend() in
 * AppServiceProvider, which just points at resolve() below.
 */
class IdApiGuard extends JWTGuard
{
    public static function resolve(Application $app, string $name, array $config): self
    {
        $guard = new self(
            $app['tymon.jwt'],
            Auth::createUserProvider($config['provider']),
            $app['request'],
            $app['events'],
        );

        $guard->setTTL(
            Arr::get($config, 'ttl', $app->make('config')->get('jwt.ttl')),
        );

        $app->refresh('request', $guard, 'setRequest');

        return $guard;
    }
}
