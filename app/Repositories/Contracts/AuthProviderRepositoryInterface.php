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
     * Find the given user's identity record for the given provider, if any.
     */
    public function findByUser(AuthProviderName $provider, User $user): ?AuthProvider;

    /**
     * Merge the given data into the identity's meta (e.g. rotate a password hash).
     *
     * @param  array<string, mixed>  $meta
     */
    public function updateSecret(AuthProvider $identity, array $meta): void;

    /**
     * Mark the given user's identity for this provider as verified.
     */
    public function markVerified(AuthProviderName $provider, User $user): void;

    /**
     * Change the identifier on an existing identity (e.g. phone number).
     */
    public function changeIdentifier(AuthProvider $identity, string $newIdentifier): void;

    /**
     * Release every identity linked to the given user (account deletion):
     * mangles each identifier so its clean value becomes available to
     * someone else, then soft-deletes the identity record. The original
     * value is preserved in the identity change log, not on the row itself.
     */
    public function releaseAllForUser(User $user): void;

    /**
     * Create a new user and link it to a new identity record.
     *
     * @param  array<string, mixed>  $meta  Provider-specific secret/extra data (e.g. password hash).
     */
    public function createUserWithIdentity(
        AuthProviderName $provider,
        string $identifier,
        array $meta = [],
        bool $verified = false,
    ): User;
}
