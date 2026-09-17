<?php

namespace App\Auth;

use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Exceptions\Auth\AuthProviderDisabledException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use LogicException;

class AuthStrategyResolver
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @throws AuthProviderDisabledException
     * @throws BindingResolutionException
     */
    public function resolve(AuthProviderName $provider): AuthStrategy
    {
        $config = config("identity.providers.{$provider->value}");

        if ($config === null) {
            throw new LogicException("No strategy registered for auth provider [{$provider->value}].");
        }

        if (! ($config['enabled'] ?? false)) {
            throw new AuthProviderDisabledException($provider->value);
        }

        return $this->container->make($config['strategy']);
    }
}
