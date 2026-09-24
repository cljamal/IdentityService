<?php

namespace App\Listeners\Auth;

use App\Events\Auth\ClientDeactivated;
use App\Repositories\Contracts\AuthSessionRepositoryInterface;

/**
 * A deactivated client's Gateway already can't authenticate at all (see
 * AuthenticateClient), and IdApiGuard::user() already rejects its users'
 * tokens live on every request — this cascade isn't load-bearing for
 * security, it's so `revoked_at` actually reflects reality (audit trail,
 * "my sessions" listing) instead of sessions sitting there looking active
 * forever just because nothing happens to naturally expire them.
 */
final readonly class RevokeSessionsForDeactivatedClient
{
    public function __construct(private AuthSessionRepositoryInterface $sessions) {}

    public function handle(ClientDeactivated $event): void
    {
        $this->sessions->revokeAllForClient($event->client);
    }
}
