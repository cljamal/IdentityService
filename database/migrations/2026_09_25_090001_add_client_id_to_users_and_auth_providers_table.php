<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes identity uniqueness per client instead of globally: the same
 * phone/email/username may now legitimately belong to different people
 * under different clients. client_id is denormalized onto auth_providers
 * (not just users) because the uniqueness guarantee has to be a real
 * composite DB index — createUserWithIdentity()/changeIdentifier() already
 * depend on catching UniqueConstraintViolationException as their race
 * backstop, which a join-based check across user_id -> users.client_id
 * could not provide.
 *
 * NOT NULL from the start, no backfill: this is pre-launch, there is no
 * production data to preserve. Existing dev databases need a client seeded
 * and rows backfilled by hand first, or just `migrate:fresh`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->after('id')->constrained()->restrictOnDelete();
        });

        Schema::table('auth_providers', function (Blueprint $table) {
            $table->foreignId('client_id')->after('user_id')->constrained()->restrictOnDelete();
        });

        Schema::table('auth_providers', function (Blueprint $table) {
            $table->dropUnique(['provider', 'identifier']);
            $table->unique(['client_id', 'provider', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::table('auth_providers', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'provider', 'identifier']);
            $table->unique(['provider', 'identifier']);
        });

        Schema::table('auth_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
