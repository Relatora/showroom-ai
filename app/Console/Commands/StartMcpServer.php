<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server\Registrar;

/**
 * Runs the Showroom MCP server over stdio, signed in as one staff member.
 *
 * laravel/mcp's own `mcp:start` works, but it has no user, and every tool here needs one to
 * apply lead visibility. This picks the user first, then hands over to the same server.
 *
 * Claude Desktop launches it; see docs/MCP.md. Nothing may be written to stdout except the
 * protocol itself, so errors go to stderr.
 */
#[Signature('showroom:mcp {--user= : Email of the staff member to act as}')]
#[Description('Start the Showroom MCP server over stdio, acting as a staff member')]
class StartMcpServer extends Command
{
    public function handle(Registrar $registrar): int
    {
        $user = User::where('email', (string) $this->option('user'))->first();

        if (! $user) {
            fwrite(STDERR, "No staff member with the email \"{$this->option('user')}\". Pass --user=you@example.com.\n");

            return self::FAILURE;
        }

        Auth::setUser($user);

        $server = $registrar->getLocalServer('showroom');

        if ($server === null) {
            fwrite(STDERR, "The showroom MCP server isn't registered. Check routes/ai.php.\n");

            return self::FAILURE;
        }

        $server();

        return self::SUCCESS;
    }
}
