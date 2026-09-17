<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail: registrations, verifications, password
 * changes/resets, identifier changes (phone/email/username), etc.
 */
class IdentityChangeLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'provider', 'action', 'from_value', 'to_value'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
