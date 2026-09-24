<?php

namespace App\Repositories;

use App\Auth\CurrentClient;
use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Events\Auth\UserHasNoRole;
use App\Exceptions\Auth\IdentifierAlreadyTakenException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class EloquentAuthProviderRepository implements AuthProviderRepositoryInterface
{
    public function __construct(
        private IdentityChangeLogRepositoryInterface $history,
        private CurrentClient $currentClient,
    ) {}

    /**
     * @throws IdentifierAlreadyTakenException
     * @throws Throwable
     */
    public function firstOrCreateUser(AuthProviderName $provider, string $identifier): User
    {
        $identity = $this->findByIdentifier($provider, $identifier);

        if ($identity) {
            return $identity->userOrFail();
        }

        try {
            return $this->createUserWithIdentity($provider, $identifier, verified: true);
        } catch (IdentifierAlreadyTakenException) {
            return $this->findByIdentifier($provider, $identifier)?->userOrFail()
                ?? throw new IdentifierAlreadyTakenException($identifier);
        }
    }

    public function findByIdentifier(AuthProviderName $provider, string $identifier): ?AuthProvider
    {
        return AuthProvider::query()
            ->where('client_id', $this->currentClient->get()->id)
            ->where('provider', $provider->value)
            ->where('identifier', $identifier)
            ->first();
    }

    public function findByUser(AuthProviderName $provider, User $user): ?AuthProvider
    {
        return AuthProvider::query()
            ->where('client_id', $this->currentClient->get()->id)
            ->where('provider', $provider->value)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function updateSecret(AuthProvider $identity, array $meta): void
    {
        $identity->update([
            'meta' => array_merge($identity->meta ?? [], $meta),
        ]);
    }

    public function markVerified(AuthProviderName $provider, User $user): void
    {
        AuthProvider::query()
            ->where('client_id', $this->currentClient->get()->id)
            ->where('provider', $provider->value)
            ->where('user_id', $user->id)
            ->update(['verified_at' => now()]);
    }

    public function changeIdentifier(AuthProvider $identity, string $newIdentifier): void
    {
        try {
            $identity->update(['identifier' => $newIdentifier]);
        } catch (UniqueConstraintViolationException) {
            throw new IdentifierAlreadyTakenException($newIdentifier);
        }
    }

    public function releaseAllForUser(User $user): void
    {
        $identities = AuthProvider::query()
            ->where('client_id', $this->currentClient->get()->id)
            ->where('user_id', $user->id)
            ->get();

        foreach ($identities as $identity) {
            $provider = AuthProviderName::tryFrom($identity->provider);
            $original = $identity->identifier;

            $identity->update(['identifier' => "{$original}::deleted::{$identity->id}"]);
            $identity->delete();

            $this->history->log($user, $provider, IdentityChangeAction::IdentifierReleased, $original, null);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     *
     * @throws Throwable
     */
    public function createUserWithIdentity(
        AuthProviderName $provider,
        string $identifier,
        array $meta = [],
        bool $verified = false,
    ): User {
        return DB::transaction(function () use ($provider, $identifier, $meta, $verified) {
            $clientId = $this->currentClient->get()->id;

            $user = User::query()->create(['client_id' => $clientId]);

            try {
                AuthProvider::query()->create([
                    'user_id' => $user->id,
                    'client_id' => $clientId,
                    'provider' => $provider->value,
                    'identifier' => $identifier,
                    'meta' => $meta ?: null,
                    'verified_at' => $verified ? now() : null,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new IdentifierAlreadyTakenException($identifier);
            }

            $this->history->log($user, $provider, IdentityChangeAction::Registered, null, $identifier);

            if ($user->roles()->doesntExist()) {
                UserHasNoRole::dispatch($user);
            }

            return $user;
        });
    }
}
