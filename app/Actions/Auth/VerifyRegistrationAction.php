<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\RegistersIdentity;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class VerifyRegistrationAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(AuthProviderName $provider, array $data): string
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof RegistersIdentity) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->registrationVerificationRules())->validate();

        $user = $strategy->verifyRegistration($data);

        return IdApiGuard::current()->login($user);
    }
}
