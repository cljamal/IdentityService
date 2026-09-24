<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\AuthProviderName;
use App\Auth\Enums\RoleName;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActsAsClient;
use Tests\TestCase;

class DefaultRoleAssignmentTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_registration_assigns_the_default_user_role(): void
    {
        $repository = app(AuthProviderRepositoryInterface::class);

        $user = $repository->createUserWithIdentity(AuthProviderName::EmailPassword, 'new@example.com');

        $this->assertTrue($user->hasRole(RoleName::User->value, 'id-api'));
    }

    public function test_the_role_is_created_once_and_reused_for_later_registrations(): void
    {
        $repository = app(AuthProviderRepositoryInterface::class);

        $repository->createUserWithIdentity(AuthProviderName::EmailPassword, 'first@example.com');
        $repository->createUserWithIdentity(AuthProviderName::EmailPassword, 'second@example.com');

        $this->assertSame(
            1,
            Role::query()->where('name', RoleName::User->value)->where('guard_name', 'id-api')->count(),
        );
    }
}
