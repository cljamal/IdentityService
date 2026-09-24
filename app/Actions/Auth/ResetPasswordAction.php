<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\TokenPair;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ResetPasswordAction
{
    use AsAction;

    public function __construct(
        private AuthStrategyResolver $resolver,
        private AuthSessionRepositoryInterface $sessions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, array $data): TokenPair
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

        // A stolen refresh token shouldn't outlive the password it was
        // issued under — revoke every existing session before issuing the
        // fresh one below.
        $this->sessions->revokeAllForUser($user);

        return IdApiGuard::current()->loginWithRefreshToken($user);
    }
}
