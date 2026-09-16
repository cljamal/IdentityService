<?php

namespace App\Repositories\Contracts;

use App\Auth\AuthProviderName;
use App\Models\User;

interface AuthProviderRepositoryInterface
{
    /**
     * Get the user linked to the given provider identity, creating both
     * the user and the identity record on first sign-in.
     */
    public function firstOrCreateUser(AuthProviderName $provider, string $identifier): User;
}
