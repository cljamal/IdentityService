<?php

namespace App\Models;

use App\Events\Auth\ClientActivated;
use App\Events\Auth\ClientDeactivated;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A downstream project this identity service issues identities for. See
 * the client_id columns on User/AuthProvider for how isolation is enforced.
 *
 * @property int $id
 * @property string $client_id
 * @property string $client_secret_hash
 * @property string $name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 */
final class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = ['client_id', 'client_secret_hash', 'name', 'is_active'];

    protected $hidden = ['client_secret_hash'];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $client): void {
            if (! $client->wasChanged('is_active')) {
                return;
            }

            if ($client->is_active) {
                ClientActivated::dispatch($client);
            } else {
                ClientDeactivated::dispatch($client);
            }
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
