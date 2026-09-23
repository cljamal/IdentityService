<?php

namespace App\Listeners\Auth;

use App\Auth\Enums\RoleName;
use App\Events\Auth\UserHasNoRole;
use App\Repositories\Contracts\RoleRepositoryInterface;

class AssignDefaultRole
{
    public function __construct(private readonly RoleRepositoryInterface $roles) {}

    public function handle(UserHasNoRole $event): void
    {
        // Guard explicit: this model authenticates only via "id-api" here,
        // not the unused default "web" guard Guard::getDefaultName() would
        // otherwise pick from config('auth.defaults.guard').
        $role = $this->roles->findOrCreate(RoleName::User, 'id-api');

        $event->user->assignRole($role);
    }
}
