<?php

namespace App\Actions\Auth;

use App\Auth\AuthProviderName;
use App\Auth\AuthStrategyResolver;
use App\Auth\Strategies\Contracts\IssuesVerificationCode;
use App\Exceptions\Auth\OtpThrottledException;
use App\Exceptions\Auth\UnsupportedAuthOperationException;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class SendVerificationCodeAction
{
    use AsAction;

    public function __construct(private readonly AuthStrategyResolver $resolver)
    {
    }

    /**
     * @throws OtpThrottledException
     */
    public function handle(AuthProviderName $provider, array $data): void
    {
        $strategy = $this->resolver->resolve($provider);

        Validator::make($data, $strategy->codeRules())->validate();

        $strategy->sendCode($data);
    }
}
