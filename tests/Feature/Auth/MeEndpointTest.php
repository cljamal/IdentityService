<?php

namespace Tests\Feature\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_decodes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = IdApiGuard::current()->login($user);

        $response = $this->withToken($token)->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('claims.sub', $user->id)
            ->assertJsonStructure([
                'user' => ['id', 'name'],
                'claims' => ['sub', 'iat', 'exp', 'jti'],
                'readable' => ['issued_at', 'expires_at', 'age_seconds', 'expires_in_seconds'],
            ]);
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
