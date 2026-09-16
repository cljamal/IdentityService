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
        $identity = AuthProvider::query()
            ->where('provider', $provider->value)
            ->where('identifier', $identifier)
            ->first();

        if ($identity) {
            return $identity->user;
        }

        return DB::transaction(function () use ($provider, $identifier) {
            $user = User::query()->create([]);

            AuthProvider::query()->create([
                'user_id' => $user->id,
                'provider' => $provider->value,
                'identifier' => $identifier,
                'verified_at' => now(),
            ]);

            return $user;
        });
    }
}
