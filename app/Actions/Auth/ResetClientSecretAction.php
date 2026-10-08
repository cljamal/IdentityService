<?php

namespace App\Actions\Auth;

use App\Auth\ClientSecret;
use App\Models\Client;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class ResetClientSecretAction
{
    use AsAction;

    public function handle(Client $client): ClientSecret
    {
        $secret = ClientSecret::generate();

        $client->fill(['client_secret_hash' => $secret->hash]);
        $client->saveOrFail();

        return $secret;
    }

    public function getCommandSignature(): string
    {
        return 'client:reset-secret {client : Публичный client_id клиента (X-Client-Id)}';
    }

    public function getCommandDescription(): string
    {
        return 'Сбросить секрет клиента и один раз показать новые credentials';
    }

    public function asCommand(Command $command): int
    {
        $client = Client::query()->where('client_id', $command->argument('client'))->first();

        if ($client === null) {
            $command->error('Клиент с указанным client_id не найден.');

            return Command::FAILURE;
        }

        $secret = $this->handle($client);

        $command->info("Секрет клиента [{$client->name}] обновлён.");
        $command->newLine();
        $command->line("  X-Client-Id:     {$client->client_id}");
        $command->line("  X-Client-Secret: {$secret->plainText}");
        $command->newLine();
        $command->warn('Сохраните секрет сейчас — повторно он не будет показан. Старый секрет больше не действует.');

        return Command::SUCCESS;
    }
}
