<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The requested session doesn't exist, isn't active, or (most likely)
 * belongs to another user — deliberately indistinguishable from "not
 * found" so a session id can't be used to probe other users' sessions.
 */
final class SessionNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Session not found.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Сессия не найдена.'], 404);
    }
}
