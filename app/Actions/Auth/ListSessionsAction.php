<?php

namespace App\Actions\Auth;

use App\Models\AuthSession;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ListSessionsAction
{
    use AsAction;

    public function __construct(private AuthSessionRepositoryInterface $sessions) {}

    /**
     * @return Collection<int, AuthSession>
     */
    public function handle(User $user, ?string $currentJti): Collection
    {
        return $this->sessions->activeForUser($user)->each(
            fn ($session) => $session->setAttribute('is_current', $session->jti === $currentJti)
        );
    }
}
