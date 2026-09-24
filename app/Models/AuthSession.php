<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per logical session ("logged in from this device"), not per
 * token: it tracks both the current access token (by its "jti" claim) and
 * the opaque refresh token that can mint the next one. Refreshing rotates
 * both jti and refresh_token_hash on the same row instead of creating a
 * new one; previous_refresh_token_hash keeps last generation's refresh
 * hash around just long enough to recognize a replayed one as reuse.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $jti
 * @property string|null $refresh_token_hash
 * @property string|null $previous_refresh_token_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $last_used_at
 * @property Carbon $expires_at
 * @property Carbon|null $refresh_expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property bool|null $is_current Transient, set by ListSessionsAction — not a column.
 * @property-read User|null $user
 */
class AuthSession extends Model
{
    protected $fillable = [
        'user_id', 'jti', 'refresh_token_hash', 'previous_refresh_token_hash',
        'ip_address', 'user_agent', 'last_used_at', 'expires_at', 'refresh_expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
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
