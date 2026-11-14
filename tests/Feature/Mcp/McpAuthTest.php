<?php

use App\Http\Controllers\Settings\McpTokenController;
use App\Models\Lead;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The HTTP door: token in, JSON-RPC out. The tool behaviour itself is in ShowroomToolsTest.
 */
function rpc(string $method, array $params = [], int $id = 1): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
}

function mcpHeaders(?string $token): array
{
    return array_filter([
        'Authorization' => $token ? "Bearer {$token}" : null,
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2025-06-18',
    ]);
}

it('needs a token', function () {
    $this->postJson('/mcp', rpc('tools/list'), mcpHeaders(null))->assertUnauthorized();
});

it('lists the tools for a valid token', function () {
    $token = User::factory()->create()->createToken('test', [McpTokenController::ABILITY])->plainTextToken;

    $tools = $this->postJson('/mcp', rpc('tools/list'), mcpHeaders($token))
        ->assertOk()
        ->json('result.tools.*.name');

    expect($tools)->toEqualCanonicalizing([
        'pipeline_summary', 'search_leads', 'get_customer_timeline',
        'find_matching_vehicles', 'search_knowledge_base', 'log_activity',
    ]);
});

it('runs tools as the token owner', function () {
    $rep = User::factory()->create();
    Lead::factory()->assignedTo($rep)->create();
    Lead::factory()->count(3)->create();
    $token = $rep->createToken('test', [McpTokenController::ABILITY])->plainTextToken;

    $this->postJson('/mcp', rpc('tools/call', ['name' => 'search_leads', 'arguments' => []]), mcpHeaders($token))
        ->assertOk()
        ->assertJsonPath('result.structuredContent.count', 1);
});

it('rejects a token issued for something else', function () {
    $token = User::factory()->create()->createToken('other', ['reports'])->plainTextToken;

    $this->postJson('/mcp', rpc('tools/list'), mcpHeaders($token))->assertForbidden();
});

it('lets people create, see and revoke their own tokens', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('mcp-tokens.store'), ['name' => 'Claude Desktop'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.newToken');

    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe([McpTokenController::ABILITY]);

    $this->actingAs($user)->get(route('mcp-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/McpAccess')
            ->where('tokens.0.name', 'Claude Desktop')
            ->missing('tokens.0.token'));

    // Someone else can't revoke it...
    $this->actingAs(User::factory()->create())->delete(route('mcp-tokens.destroy', $token->id));
    expect($user->tokens()->count())->toBe(1);

    // ...but its owner can.
    $this->actingAs($user)->delete(route('mcp-tokens.destroy', $token->id));
    expect($user->tokens()->count())->toBe(0);
});

it('refuses to start the stdio server without a real user', function () {
    $this->artisan('showroom:mcp', ['--user' => 'nobody@example.com'])->assertFailed();
});
