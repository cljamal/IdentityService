<?php

namespace App\Actions\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Auth\TokenPair;
use App\Exceptions\Auth\InvalidRefreshTokenException;
use App\Exceptions\Auth\RefreshTokenReusedException;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RefreshTokenAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidRefreshTokenException
     * @throws RefreshTokenReusedException
     */
    public function handle(array $data): TokenPair
    {
        // A missing/malformed refresh_token is treated the same as an
        // unknown one (401), not a 422 validation error — an old client
        // still sending the previous Bearer-only refresh contract must
        // land on the same "your session is gone, log in again" path,
        // not a shape it has no handling for.
        if (! isset($data['refresh_token']) || ! is_string($data['refresh_token']) || $data['refresh_token'] === '') {
            throw new InvalidRefreshTokenException;
        }

        return IdApiGuard::current()->refreshUsingToken($data['refresh_token']);
    }
}
