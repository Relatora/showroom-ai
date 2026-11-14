<?php

use App\Mcp\Servers\ShowroomServer;
use Laravel\Mcp\Facades\Mcp;

/*
| MCP servers (docs/MCP.md).
|
| Over HTTP, clients authenticate with a personal access token from Settings → MCP access.
| The token must carry the "mcp" ability, and calls have their own per-user rate limit.
|
| Over stdio (Claude Desktop launching a local process), there's no HTTP request to carry a
| token, so `php artisan showroom:mcp --user=you@example.com` signs in as that user. Anyone who
| can run artisan already has the whole database, so that's no weaker.
*/

Mcp::web('/mcp', ShowroomServer::class)
    ->middleware(['auth:sanctum', 'abilities:mcp', 'throttle:mcp']);

Mcp::local('showroom', ShowroomServer::class);
