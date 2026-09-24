<?php

namespace Tests\Feature\Auth;

use App\Auth\Enums\RoleName;
use App\Auth\Guards\IdApiGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_decodes_the_current_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(RoleName::User, 'id-api'));

        $tokens = IdApiGuard::current()->login($user);

        $response = $this->withToken($tokens->accessToken)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.uuid', $user->uuid)
            ->assertJsonPath('user.role', RoleName::User->value)
            ->assertJsonPath('claims.sub', $user->uuid)
            ->assertJsonPath('claims.role', RoleName::User->value)
            ->assertJsonStructure([
                'user' => ['uuid', 'role'],
                'claims' => ['sub', 'iat', 'exp', 'jti', 'role'],
                'readable' => ['issued_at', 'expires_at', 'age_seconds', 'expires_in_seconds'],
            ]);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
