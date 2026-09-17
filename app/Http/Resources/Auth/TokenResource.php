<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

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
            // Реальный TTL гварда, а не глобальный config('jwt.ttl') —
            // они разъедутся, если когда-нибудь переопределить ttl для
            // конкретного гварда в config/auth.php.
            'expires_in' => Auth::guard('id-api')->getTTL() * 60,
        ];
    }
}
