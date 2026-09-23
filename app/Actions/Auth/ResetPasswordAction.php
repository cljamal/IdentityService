<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class ResetPasswordAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, array $data): string
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof ResetsPassword) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->passwordResetRules())->validate();

        $user = $strategy->resetPassword($data);

        return IdApiGuard::current()->login($user);
    }
}
