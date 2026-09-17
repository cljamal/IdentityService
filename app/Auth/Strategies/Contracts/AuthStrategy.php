<?php

namespace App\Auth\Strategies\Contracts;

use App\Models\User;

interface AuthStrategy
{
    /**
     * Validation rules for the authenticate/login step.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Resolve (and create, if needed) the user from validated input.
     *
     * @param  array<string, mixed>  $data
     */
    public function authenticate(array $data): User;
}
