<?php

namespace App\Http\Controllers;

use App\Actions\Auth\ListSessionsAction;
use App\Actions\Auth\RevokeSessionAction;
use App\Auth\Guards\IdApiGuard;
use App\Http\Resources\Auth\SessionResource;
use App\Http\Resources\MessageResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SessionController extends Controller
{
    /**
     * List the authenticated user's active sessions (one per issued JWT,
     * refreshes rotate the existing entry rather than adding a new one).
     */
    public function index(): AnonymousResourceCollection
    {
        $guard = IdApiGuard::current();

        $sessions = ListSessionsAction::run($guard->user(), $guard->getPayload()->get('jti'));

        return SessionResource::collection($sessions);
    }

    /**
     * Revoke a specific session (e.g. "log out this device" remotely).
     */
    public function destroy(int $session): MessageResource
    {
        RevokeSessionAction::run(IdApiGuard::current()->user(), $session);

        return MessageResource::make('Сессия отозвана.');
    }
}
