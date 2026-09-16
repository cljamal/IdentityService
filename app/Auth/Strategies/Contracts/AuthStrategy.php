<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

interface AuthStrategy
{
    /**
     * Validation rules for the authenticate/login step.
     */
    public function rules(): array;

    /**
     * Resolve (and create, if needed) the user from validated input.
     */
    public function authenticate(array $data): User;
}
