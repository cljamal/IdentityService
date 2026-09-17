<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['email', 'email_verified_at', 'password', 'remember_token']);
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Best-effort: the dropped data is gone for good, and `name` no
        // longer gets set by any current registration path (createUserWithIdentity
        // never sets it), so nothing here can be restored as NOT NULL without
        // crashing on a table that already has rows — these come back nullable.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->nullable()->after('email_verified_at');
            $table->rememberToken()->after('password');
        });
    }
};
