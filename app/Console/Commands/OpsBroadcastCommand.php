<?php

namespace App\Console\Commands;

use App\Events\Ops\TestBroadcast;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Manual trigger for ops broadcasts, since there's no admin panel. Only
 * covers actions that either go through a real state change (so the
 * client's own event pipeline fires it, nothing spoofed) or are an
 * unmistakably fake diagnostic ping — deliberately NOT a generic "fire any
 * event with any arguments" dispatcher: telling a client "this user's
 * account was deleted" when it wasn't is worse than not having the tool at
 * all, since the client has no way to tell a real signal from a wrong one.
 */
final class OpsBroadcastCommand extends Command
{
    protected $signature = 'ops:broadcast
        {action : service-enable|service-disable|test}
        {client : Public client_id (X-Client-Id) of the target client}
        {--message= : Message body for a "test" broadcast}';

    protected $description = 'Manually trigger an ops broadcast for a client (no admin panel exists yet)';

    public function handle(): int
    {
        $client = Client::query()->where('client_id', $this->argument('client'))->first();

        if ($client === null) {
            $this->components->error("No client found for client_id [{$this->argument('client')}].");

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'service-enable' => $this->setActive($client, true),
            'service-disable' => $this->setActive($client, false),
            'test' => $this->test($client),
            default => $this->unknownAction(),
        };
    }

    private function setActive(Client $client, bool $active): int
    {
        if ($client->is_active === $active) {
            $this->components->warn(
                "Client [{$client->name}] is already ".($active ? 'active' : 'inactive').' — no event fired.'
            );

            return self::SUCCESS;
        }

        // Client::booted() dispatches ClientActivated/ClientDeactivated on
        // this update, which in turn fire ServiceEnabled/ServiceDisabled
        // (and, for disable, cascade session revocation) — nothing here
        // dispatches an ops event directly.
        $client->update(['is_active' => $active]);

        $this->components->info(
            "Client [{$client->name}] is now ".($active ? 'active' : 'inactive').'.'
        );

        return self::SUCCESS;
    }

    private function test(Client $client): int
    {
        $message = $this->option('message') ?? 'Manual test broadcast from ops:broadcast';

        TestBroadcast::dispatch($client, $message);

        $this->components->info('Dispatched.');
        $this->line("  Channel: private-client.{$client->client_id}");
        $this->line('  Event:   ops.test');

        return self::SUCCESS;
    }

    private function unknownAction(): int
    {
        $this->components->error("Unknown action [{$this->argument('action')}]. Use service-enable, service-disable, or test.");

        return self::FAILURE;
    }
}
