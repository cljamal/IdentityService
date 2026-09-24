<?php

namespace Tests\Feature\Broadcasting;

use App\Events\Ops\TestBroadcast;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Regression test for the bug found while building this feature:
 * config/broadcasting.php didn't exist at all, so config('broadcasting.default')
 * was always null and BroadcastManager silently fell back to its own
 * hardcoded 'null' driver (a complete no-op) regardless of .env's
 * BROADCAST_CONNECTION — meaning no broadcast, old or new, ever reached
 * even the log. phpunit.xml forces BROADCAST_CONNECTION=null for tests, so
 * this explicitly sets 'log' to exercise the config that actually ships.
 */
class BroadcastingConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_driver_actually_receives_a_broadcast(): void
    {
        config(['broadcasting.default' => 'log']);

        Log::shouldReceive('info')
            ->once()
            ->with(\Mockery::pattern('/Broadcasting \[ops\.test\]/'));

        $client = Client::factory()->create();

        TestBroadcast::dispatch($client, 'smoke test');
    }
}
