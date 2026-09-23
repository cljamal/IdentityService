<?php

namespace App\Actions\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\IssuesVerificationCode;
use App\Auth\Strategies\Contracts\NormalizesInput;
use App\Exceptions\Auth\AuthProviderDisabledException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class SendVerificationCodeAction
{
    use AsAction;

    public function __construct(private AuthStrategyResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws OtpThrottledException|AuthProviderDisabledException|UnsupportedAuthOperationException
     */
    public function handle(AuthProviderName $provider, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        if (! $strategy instanceof IssuesVerificationCode) {
            throw new UnsupportedAuthOperationException($provider->value);
        }

        if ($strategy instanceof NormalizesInput) {
            $data = $strategy->normalize($data);
        }

        Validator::make($data, $strategy->codeRules())->validate();

        $strategy->sendCode($data);
    }
}
