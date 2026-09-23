<?php

namespace App\Repositories;

use App\Auth\Enums\RoleName;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Models\Role as RoleModel;

final class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function findOrCreate(RoleName $name, string $guard): Role
    {
        return RoleModel::findOrCreate($name, $guard);
    }
}
