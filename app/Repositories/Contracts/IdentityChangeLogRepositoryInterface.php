<?php

namespace App\Repositories\Contracts;

use App\Auth\Enums\AuthProviderName;
use App\Auth\History\IdentityChangeAction;
use App\Models\User;

interface IdentityChangeLogRepositoryInterface
{
    public function log(
        User $user,
        ?AuthProviderName $provider,
        IdentityChangeAction $action,
        ?string $from,
        ?string $to,
    ): void;
}
