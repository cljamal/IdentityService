<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\RegistersIdentity;
use App\Exceptions\Auth\AuthProviderDisabledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class RegisterAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver)
    {
    }

    /**
     * @throws AuthProviderDisabledException
     * @throws UnsupportedAuthOperationException
     */
    public function handle(AuthProviderName $provider, array $data): string
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof RegistersIdentity) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        Validator::make($data, $strategy->registrationRules())->validate();

        $user = $strategy->register($data);

        return Auth::guard('api')->login($user);
    }
}
