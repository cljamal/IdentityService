<?php

namespace App\Actions\Auth;

use App\Auth\Enums\SessionRevocationReason;
use App\Events\Ops\UserSessionRevoked;
use App\Exceptions\Auth\SessionNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RevokeSessionAction
{
    use AsAction;

    public function __construct(private AuthSessionRepositoryInterface $sessions) {}

    /**
     * @throws SessionNotFoundException
     */
    public function handle(User $user, int $sessionId): void
    {
        $session = $this->sessions->findActiveForUser($user, $sessionId);

        if (! $session) {
            throw new SessionNotFoundException;
        }

        $this->sessions->revoke($session);

        UserSessionRevoked::dispatch($user, SessionRevocationReason::ExplicitRevoke);
    }
}
