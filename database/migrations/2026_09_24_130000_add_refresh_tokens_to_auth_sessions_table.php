<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backing columns for opaque, rotating refresh tokens (see IdApiGuard).
 * `refresh_token_hash` is the currently valid one; `previous_refresh_token_hash`
 * is the one it replaced, kept for exactly one generation so a replay of an
 * already-rotated token is recognized as reuse (token leaked) rather than
 * just "not found". Only hashes are ever stored — the plaintext token is
 * shown to the client once, at issue/rotation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->string('refresh_token_hash', 64)->nullable()->unique()->after('jti');
            $table->string('previous_refresh_token_hash', 64)->nullable()->after('refresh_token_hash');
            $table->timestamp('refresh_expires_at')->nullable()->after('expires_at');

            $table->index('previous_refresh_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table) {
            $table->dropUnique(['refresh_token_hash']);
            $table->dropIndex(['previous_refresh_token_hash']);
            $table->dropColumn(['refresh_token_hash', 'previous_refresh_token_hash', 'refresh_expires_at']);
        });
    }
};
