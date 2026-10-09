<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Enums\SessionRevocationReason;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\ResetsPassword;
use App\Auth\TokenPair;
use App\Events\Ops\UserSessionRevoked;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use App\Models\User;
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

        $user = $strategy->resetPassword($data, function (User $user): void {
            // A stolen refresh token shouldn't outlive the password it was issued under.
            $this->sessions->revokeAllForUser($user);
        });

        UserSessionRevoked::dispatch($user, SessionRevocationReason::PasswordReset);

        return IdApiGuard::current()->loginWithRefreshToken($user);
    }
}
