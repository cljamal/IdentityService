<?php

namespace Database\Factories;

use App\Auth\ClientSecret;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * The plaintext secret every factory-made Client is given — fixed so
     * tests can put it straight into an X-Client-Secret header without
     * threading a return value out of the factory.
     */
    public const PLAIN_SECRET = 'test-client-secret';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Str::random(32),
            'client_secret_hash' => ClientSecret::hash(self::PLAIN_SECRET),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
