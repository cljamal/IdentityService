<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int $client_id
 * @property string $uuid
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 * @property-read Collection<int, AuthProvider> $authProviders
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = ['client_id'];

    /**
     * Tell HasUuids to populate `uuid`, not the `id` primary key — `id`
     * stays a plain auto-increment int used only for FKs/indexing.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<AuthProvider, $this>
     */
    public function authProviders(): HasMany
    {
        return $this->hasMany(AuthProvider::class);
    }

    /**
     * The public, external-facing identifier. `id` stays internal
     * (FKs, indexing) and is never exposed to clients or tokens.
     */
    public function getAuthIdentifierName(): string
    {
        return 'uuid';
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getAuthIdentifier();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->roles()->where('guard_name', 'id-api')->value('name'),
            'client_id' => $this->client->client_id,
        ];
    }
}
