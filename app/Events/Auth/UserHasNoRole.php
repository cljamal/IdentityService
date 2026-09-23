<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired right after a user is created with zero roles assigned. Kept
 * specific to "this registration context has no default role yet" rather
 * than a generic "user registered" event, so a future context (contractor,
 * ...) gets its own event + listener pair assigning its own RoleName case,
 * instead of one listener branching on a role parameter.
 */
class UserHasNoRole
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
