<?php

namespace App\Repositories;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Models\IdentityChangeLog;
use App\Models\User;
use App\Repositories\Contracts\IdentityChangeLogRepositoryInterface;

class EloquentIdentityChangeLogRepository implements IdentityChangeLogRepositoryInterface
{
    public function log(
        User $user,
        ?AuthProviderName $provider,
        IdentityChangeAction $action,
        ?string $from,
        ?string $to,
    ): void {
        IdentityChangeLog::query()->create([
            'user_id' => $user->id,
            'provider' => $provider?->value,
            'action' => $action->value,
            'from_value' => $from,
            'to_value' => $to,
        ]);
    }
}
