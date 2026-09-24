<?php

namespace App\Events\Auth;

use App\Models\Client;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class ClientDeactivated
{
    use Dispatchable;

    public function __construct(public Client $client) {}
}
