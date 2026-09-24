<?php

namespace App\Console\Commands;

use App\Auth\ClientSecret;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CreateClientCommand extends Command
{
    protected $signature = 'client:create {name : Human-readable label for the client}';

    protected $description = 'Provision a new downstream client and print its one-time credentials';

    public function handle(): int
    {
        $secret = ClientSecret::generate();

        $client = Client::query()->create([
            'client_id' => Str::random(32),
            'client_secret_hash' => $secret->hash,
            'name' => $this->argument('name'),
            'is_active' => true,
        ]);

        $this->components->info("Client [{$client->name}] created.");
        $this->newLine();
        $this->line("  X-Client-Id:     {$client->client_id}");
        $this->line("  X-Client-Secret: {$secret->plainText}");
        $this->newLine();
        $this->components->warn('Store the secret now — it will not be shown again.');

        return self::SUCCESS;
    }
}
