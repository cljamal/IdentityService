<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\DeleteAccountAction;
use App\Auth\Enums\AuthProviderName;
use App\Auth\Guards\IdApiGuard;
use App\Models\AuthProvider;
use App\Repositories\Contracts\AuthProviderRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\ActsAsClient;
use Tests\TestCase;

class AccountDeletionIdentifierLengthTest extends TestCase
{
    use ActsAsClient, RefreshDatabase;

    public function test_deleting_a_maximum_length_identifier_releases_it_for_registration(): void
    {
        $identifier = str_repeat('a', 255);
        $providers = $this->app->make(AuthProviderRepositoryInterface::class);
        $user = $providers->createUserWithIdentity(
            AuthProviderName::UsernamePassword,
            $identifier,
            ['password' => bcrypt('password123')],
            verified: true,
        );
        IdApiGuard::current()->loginWithRefreshToken($user);

        Event::fake();
        DeleteAccountAction::run($user);

        $released = AuthProvider::withTrashed()->where('user_id', $user->id)
            ->where('provider', AuthProviderName::UsernamePassword->value)
            ->firstOrFail();

        $this->assertLessThanOrEqual(255, strlen($released->identifier));
        $this->assertNotSame($identifier, $released->identifier);
        $this->assertDatabaseHas('identity_change_logs', [
            'action' => 'identifier_released',
            'from_value' => $identifier,
        ]);

        $this->postJson('/api/auth/username-password/register', [
            'username' => $identifier,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();
    }
}
