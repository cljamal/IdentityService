<?php

namespace App\Auth;

use App\Auth\Strategies\Contracts\AuthStrategy;
use App\Auth\Strategies\PhoneOtpStrategy;
use Illuminate\Contracts\Container\Container;
use LogicException;

class AuthStrategyResolver
{
    /** @var array<string, class-string<AuthStrategy>> */
    private array $strategies = [
        AuthProviderName::PhoneOtp->value => PhoneOtpStrategy::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function resolve(AuthProviderName $provider): AuthStrategy
    {
        $class = $this->strategies[$provider->value]
            ?? throw new LogicException("No strategy registered for auth provider [{$provider->value}].");

        return $this->container->make($class);
    }
}
