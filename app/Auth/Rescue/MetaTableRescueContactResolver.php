<?php

namespace App\Auth\Rescue;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Looks a rescue contact up in a generic EAV-style meta table
 * (e.g. user_metas: user_id / meta_key / meta_value), fully
 * configurable so it isn't tied to one specific schema.
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

    public function resolve(User $user): ?string
    {
        if (! Schema::hasTable($this->table)) {
            return null;
        }

        return DB::table($this->table)
            ->where($this->userIdColumn, $user->id)
            ->where($this->keyColumn, $this->metaKey)
            ->value($this->valueColumn);
    }
}
