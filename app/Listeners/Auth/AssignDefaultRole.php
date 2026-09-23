<?php

namespace App\Listeners\Auth;

use App\Auth\Enums\RoleName;
use App\Events\Auth\UserHasNoRole;
use App\Repositories\Contracts\RoleRepositoryInterface;

final readonly class AssignDefaultRole
{
    public function __construct(private RoleRepositoryInterface $roles) {}

    public function handle(UserHasNoRole $event): void
    {
        $role = $this->roles->findOrCreate(RoleName::User, 'id-api');
        $event->user->assignRole($role);
    }
}
