<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class LoginAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver)
    {
    }

    public function handle(AuthProviderName $provider, array $data): string
    {
        $strategy = $this->resolver->resolve($provider);

        Validator::make($data, $strategy->rules())->validate();

        $user = $strategy->authenticate($data);

        return Auth::guard('id-api')->login($user);
    }
}
