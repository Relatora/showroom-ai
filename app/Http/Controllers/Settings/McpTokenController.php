<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens for connecting an MCP client (Claude Desktop, an IDE) over HTTP.
 *
 * A token acts as its owner, with the owner's visibility, so it's as powerful as their login.
 * That's why it's shown exactly once, stored only as a hash (Sanctum does this), shows when it
 * was last used, and can be revoked with one click.
 */
class McpTokenController extends Controller
{
    /** Tokens only open the MCP endpoint, not the rest of the app. */
    public const ABILITY = 'mcp';

    public function index(Request $request): Response
    {
        return Inertia::render('settings/McpAccess', [
            'tokens' => $request->user()->tokens()
                ->latest()
                ->get()
                ->map(fn (PersonalAccessToken $token) => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ]),
            'endpoint' => url('/mcp'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);

        $token = $request->user()->createToken($data['name'], [self::ABILITY]);

        // Flash, not a prop: the plain-text token exists for this one response and never again.
        Inertia::flash('newToken', $token->plainTextToken);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Token created. Copy it now; it won\'t be shown again.']);

        return back();
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        // Scoped to the user's own tokens, so nobody can revoke someone else's by guessing an id.
        $request->user()->tokens()->whereKey($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Token revoked. Anything using it is disconnected.']);

        return back();
    }
}
