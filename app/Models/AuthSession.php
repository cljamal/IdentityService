<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per issued JWT (keyed by its "jti" claim). Refreshing a token
 * rotates the jti on the same row rather than creating a new one, so this
 * reflects logical sessions ("logged in from this device"), not raw tokens.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $jti
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $last_used_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property bool|null $is_current Transient, set by ListSessionsAction — not a column.
 *
 * @property-read User|null $user
 */
class AuthSession extends Model
{
    protected $fillable = ['user_id', 'jti', 'ip_address', 'user_agent', 'last_used_at', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
