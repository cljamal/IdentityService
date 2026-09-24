<?php

namespace App\Http\Resources\Auth;

use App\Auth\Guards\IdApiGuard;
use App\Auth\TokenPair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TokenResource extends JsonResource
{
    public function __construct(private readonly TokenPair $tokens)
    {
        parent::__construct(null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->tokens->accessToken,
            'refresh_token' => $this->tokens->refreshToken,
            'token_type' => 'bearer',
            // Реальный TTL гварда, а не глобальный config('jwt.ttl') —
            // они разъедутся, если когда-нибудь переопределить ttl для
            // конкретного гварда в config/auth.php.
            'expires_in' => IdApiGuard::current()->getTTL() * 60,
            'refresh_expires_in' => (int) config('identity.refresh_token_ttl') * 60,
        ];
    }
}
