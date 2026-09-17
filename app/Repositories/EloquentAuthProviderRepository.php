<?php

namespace App\Repositories;

use App\Auth\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Exceptions\Auth\IdentifierAlreadyTakenException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentAuthProviderRepository implements AuthProviderRepositoryInterface
{
    public function __construct(private readonly IdentityChangeLogRepositoryInterface $history) {}

    public function firstOrCreateUser(AuthProviderName $provider, string $identifier): User
    {
        $identity = $this->findByIdentifier($provider, $identifier);

        if ($identity) {
            return $identity->userOrFail();
        }

        try {
            return $this->createUserWithIdentity($provider, $identifier, verified: true);
        } catch (IdentifierAlreadyTakenException) {
            // Гонка: два одновременных запроса с одним и тем же телефоном —
            // не ошибка, конкурент просто успел создать identity первым.
            return $this->findByIdentifier($provider, $identifier)?->userOrFail()
                ?? throw new IdentifierAlreadyTakenException($identifier);
        }
    }

    public function findByIdentifier(AuthProviderName $provider, string $identifier): ?AuthProvider
    {
        return AuthProvider::query()
            ->where('provider', $provider->value)
            ->where('identifier', $identifier)
            ->first();
    }

    public function findByUser(AuthProviderName $provider, User $user): ?AuthProvider
    {
        return AuthProvider::query()
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
        $identities = AuthProvider::query()->where('user_id', $user->id)->get();

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
     */
    public function createUserWithIdentity(
        AuthProviderName $provider,
        string $identifier,
        array $meta = [],
        bool $verified = false,
    ): User {
        try {
            $user = DB::transaction(function () use ($provider, $identifier, $meta, $verified) {
                $user = User::query()->create([]);

                AuthProvider::query()->create([
                    'user_id' => $user->id,
                    'provider' => $provider->value,
                    'identifier' => $identifier,
                    'meta' => $meta ?: null,
                    'verified_at' => $verified ? now() : null,
                ]);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new IdentifierAlreadyTakenException($identifier);
        }

        $this->history->log($user, $provider, IdentityChangeAction::Registered, null, $identifier);

        return $user;
    }
}
