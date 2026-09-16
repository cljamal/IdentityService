<?php

namespace App\Repositories\Contracts;

use App\Auth\AuthProviderName;
use App\Models\AuthProvider;
use App\Models\User;

interface AuthProviderRepositoryInterface
{
    /**
     * Get the user linked to the given provider identity, creating both
     * the user and the identity record on first sign-in.
     */
    public function firstOrCreateUser(AuthProviderName $provider, string $identifier): User;

    /**
     * Find the identity record for the given provider, if any.
     */
    public function findByIdentifier(AuthProviderName $provider, string $identifier): ?AuthProvider;

    /**
     * Create a new user and link it to a new identity record.
     *
     * @param  array  $meta  Provider-specific secret/extra data (e.g. password hash).
     */
    public function createUserWithIdentity(
        AuthProviderName $provider,
        string $identifier,
        array $meta = [],
        bool $verified = false,
    ): User;
}
