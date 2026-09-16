<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function __construct(private readonly string $token)
    {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }
}
