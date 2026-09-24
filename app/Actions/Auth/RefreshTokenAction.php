<?php

namespace App\Actions\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Auth\TokenPair;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RefreshTokenAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): TokenPair
    {
        Validator::make($data, ['refresh_token' => ['required', 'string']])->validate();

        return IdApiGuard::current()->refreshUsingToken($data['refresh_token']);
    }
}
