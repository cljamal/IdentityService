<?php

namespace App\Repositories;

use App\Auth\AuthProviderName;
use App\Exceptions\Auth\IdentifierAlreadyTakenException;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentAuthProviderRepository implements AuthProviderRepositoryInterface
{
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
            return DB::transaction(function () use ($provider, $identifier, $meta, $verified) {
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
    }
}
