<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\TokenPair;
use App\Exceptions\Auth\AuthProviderDisabledException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class LoginAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthProviderDisabledException
     * @throws BindingResolutionException
     */
    public function handle(AuthProviderName $provider, array $data): TokenPair
    {
        $strategy = $this->resolver->resolve($provider);

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->rules())->validate();

        $user = $strategy->authenticate($data);

        return IdApiGuard::current()->loginWithRefreshToken($user);
    }
}
