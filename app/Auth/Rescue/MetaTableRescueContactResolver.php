<?php

namespace App\Auth\Rescue;

use App\Models\User;
use App\Notifications\Otp\OtpChannel;
use App\Notifications\Otp\OtpDestination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Looks a rescue contact up in a generic EAV-style meta table
 * (e.g. user_metas: user_id / meta_key / meta_value), fully
 * configurable so it isn't tied to one specific schema.
 *
 * The resolved value is always treated as an email address — the only
 * configured default (AUTH_USERNAME_PASSWORD_RESCUE_META_KEY=email) is
 * one, and this generic EAV lookup has no other way to know the channel.
 * If you ever point meta_key at something else, this needs revisiting.
 */
final readonly class MetaTableRescueContactResolver implements RescueContactResolver
{
    public function __construct(
        private string $table,
        private string $userIdColumn,
        private string $keyColumn,
        private string $valueColumn,
        private string $metaKey,
    ) {}

    public function resolve(User $user): ?OtpDestination
    {
        if (! Schema::hasTable($this->table)) {
            return null;
        }

        $value = DB::table($this->table)
            ->where($this->userIdColumn, $user->id)
            ->where($this->keyColumn, $this->metaKey)
            ->value($this->valueColumn);

        return $value !== null ? new OtpDestination(OtpChannel::Email, $value) : null;
    }
}
