<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class UserHasNoRole
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
