<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable audit trail: registrations, verifications, password
 * changes/resets, identifier changes (phone/email/username), etc.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $provider
 * @property string $action
 * @property string|null $from_value
 * @property string|null $to_value
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class IdentityChangeLog extends Model
{
    const null UPDATED_AT = null;

    protected $fillable = ['user_id', 'provider', 'action', 'from_value', 'to_value'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
