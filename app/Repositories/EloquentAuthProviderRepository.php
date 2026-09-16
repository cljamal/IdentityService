<?php

namespace App\Repositories;

use App\Auth\AuthProviderName;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentAuthProviderRepository implements AuthProviderRepositoryInterface
{
    public function firstOrCreateUser(AuthProviderName $provider, string $identifier): User
    {
        $identity = $this->findByIdentifier($provider, $identifier);

        if ($identity) {
            return $identity->user;
        }

        return $this->createUserWithIdentity($provider, $identifier, verified: true);
    }

    public function findByIdentifier(AuthProviderName $provider, string $identifier): ?AuthProvider
    {
        return AuthProvider::query()
            ->where('provider', $provider->value)
            ->where('identifier', $identifier)
            ->first();
    }

    public function createUserWithIdentity(
        AuthProviderName $provider,
        string $identifier,
        array $meta = [],
        bool $verified = false,
    ): User {
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
    }
}
