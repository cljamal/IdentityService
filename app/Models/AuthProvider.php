<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $verified_at
 */
class AuthProvider extends Model
{
    protected $fillable = ['user_id', 'provider', 'identifier', 'meta', 'verified_at'];

    /** meta may hold provider-specific secrets (e.g. password hash). */
    protected $hidden = ['meta'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The linked user, or a loud failure if this identity is orphaned
     * (its user was deleted — user_id is nullOnDelete). Callers that have
     * already verified a credential against this identity use this: at
     * that point a missing user means corrupted data, not "not found".
     */
    public function userOrFail(): User
    {
        return $this->user ?? throw new RuntimeException(
            "AuthProvider #{$this->id} ({$this->provider}:{$this->identifier}) has no linked user."
        );
    }
}
