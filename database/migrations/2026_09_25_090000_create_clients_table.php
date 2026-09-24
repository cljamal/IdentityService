<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "client" is a downstream project this identity service issues
 * identities for. One running instance/database serves many clients, each
 * fully isolated from the others (see the client_id scoping added to
 * users/auth_providers in the next migration) — this is not SSO, a person
 * registered under one client is unknown to any other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('client_secret_hash');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
