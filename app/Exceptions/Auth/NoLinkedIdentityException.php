<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The authenticated user has no identity for this provider to change
 * (e.g. they logged in via email-password and never linked a phone).
 */
final class NoLinkedIdentityException extends Exception
{
    public function __construct(string $provider)
    {
        parent::__construct("No {$provider} identity linked to this account.");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 400);
    }
}
