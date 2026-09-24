<?php

namespace App\Listeners\Auth;

use App\Events\Auth\ClientActivated;
use App\Events\Ops\ServiceEnabled;

final readonly class BroadcastServiceEnabled
{
    public function handle(ClientActivated $event): void
    {
        ServiceEnabled::dispatch($event->client);
    }
}
