<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

/**
 * The client-secret-authenticated counterpart to the framework's own
 * POST /api/broadcasting/auth (registered via withBroadcasting() in
 * bootstrap/app.php, behind auth:id-api). BroadcastManager::routes()
 * hardcodes that URI, so it can't be registered a second time with
 * different middleware — this is a separate route doing exactly what
 * Illuminate\Broadcasting\BroadcastController::authenticate() does.
 */
final class BroadcastingClientAuthController extends Controller
{
    public function __invoke(Request $request)
    {
        return Broadcast::auth($request);
    }
}
