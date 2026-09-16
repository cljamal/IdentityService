<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
