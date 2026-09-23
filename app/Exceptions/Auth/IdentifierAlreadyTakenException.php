<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backstop for the registration race: two concurrent requests both pass
 * the Rule::unique check (neither sees the other's row yet), then both
 * try to insert the same (provider, identifier) — the DB's unique index
 * rejects the loser. This turns that into a clean 422 instead of a raw
 * QueryException/500.
 */
class IdentifierAlreadyTakenException extends Exception
{
    public function __construct(string $identifier)
    {
        parent::__construct("Identifier [{$identifier}] is already taken.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Этот идентификатор уже занят.'], 422);
    }
}
