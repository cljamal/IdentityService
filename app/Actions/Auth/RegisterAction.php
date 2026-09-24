<?php

namespace App\Actions\Auth;

use App\Auth\AuthStrategyResolver;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Auth\Strategies\Contracts\RegistersIdentity;
use App\Auth\TokenPair;
use App\Exceptions\Auth\AuthProviderDisabledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RegisterAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     * @return TokenPair|null The tokens, or null if the identity still needs
     *                        to be verified before it can be used to log in.
     *
     * @throws AuthProviderDisabledException
     * @throws UnsupportedAuthOperationException
     */
    public function handle(AuthProviderName $provider, array $data): ?TokenPair
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof RegistersIdentity) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->registrationRules())->validate();

        $result = $strategy->register($data);

        if (! $result->verified) {
            return null;
        }

        return IdApiGuard::current()->login($result->user);
    }
}
