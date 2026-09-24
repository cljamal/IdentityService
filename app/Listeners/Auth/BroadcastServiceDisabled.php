<?php

namespace App\Listeners\Auth;

use App\Events\Auth\ClientDeactivated;
use App\Events\Ops\ServiceDisabled;

final readonly class BroadcastServiceDisabled
{
    public function handle(ClientDeactivated $event): void
    {
        ServiceDisabled::dispatch($event->client);
    }
}
