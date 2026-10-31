<?php

use App\Mcp\Servers\SectfServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// OAuth discovery (.well-known at the origin root) and
// Dynamic Client Registration for Claude Code. Redirects are loopback-only
// (config/mcp.php), registration is open, so it is throttled per IP.
Route::middleware('throttle:mcp-oauth')->group(function () {
    Mcp::oauthRoutes();
});

// mcp.origin first: a browser request from a foreign Origin (DNS rebinding)
// is refused before the bearer is read. Claude Code sends no Origin.
Mcp::web('/mcp', SectfServer::class)
    ->middleware(['mcp.origin', 'auth:api', 'throttle:mcp']);
