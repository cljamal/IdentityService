<?php

namespace App\Repositories\Contracts;

use App\Auth\Enums\RoleName;
use Spatie\Permission\Contracts\Role;

interface RoleRepositoryInterface
{
    /**
     * Find the role by name for the given guard, creating it if it
     * doesn't exist yet.
     */
    public function findOrCreate(RoleName $name, string $guard): Role;
}
