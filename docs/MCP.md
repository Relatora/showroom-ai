# Showroom over MCP

Showroom runs a [Model Context Protocol](https://modelcontextprotocol.io) server, so any MCP
client can work the CRM in plain language: Claude Desktop, an IDE, or your own agent.

> "Which of my hot leads haven't heard from us in three days, and what's in stock for them?"

The client calls `search_leads`, then `get_customer_timeline` and `find_matching_vehicles` for
each lead, and answers with links back into the CRM.

## What's exposed

### Tools

| Tool                     | Does                                                                     | Writes? |
| ------------------------ | ------------------------------------------------------------------------ | ------- |
| `pipeline_summary`       | Open leads per stage, stale count, follow-ups and appointments due today | No      |
| `search_leads`           | Find leads by text, stage, temperature or staleness, stalest first       | No      |
| `get_customer_timeline`  | One lead in full: needs, budget, trade-in, appointments, timeline        | No      |
| `find_matching_vehicles` | Available stock within ~10% of the lead's budget, with key facts         | No      |
| `search_knowledge_base`  | Hybrid search over policies, inventory and visible lead notes            | No      |
| `log_activity`           | Add a call, email, text, meeting, test drive or note to a lead           | **Yes** |

### Prompt

`daily_lead_review` (arguments: `how_many`, `focus`) is a start-of-day routine. It walks the
tools in order and ends with a checklist of who to contact and what to say.

### Resources

- `showroom://policies`: the list of dealership policies.
- `showroom://policies/{id}`: one policy in full.

## Security model

An MCP client is a second front door into the same CRM, not a back door.

- **Everything runs as a real user.** Tools go through the same `Lead::visibleTo()` scope and
  policies as the web UI. A rep's client sees that rep's leads; a manager's sees the floor.
- **"Not yours" looks the same as "doesn't exist".** Asking for another rep's lead id gives the
  same answer as asking for an id that doesn't exist, so ids can't be probed.
- **Contact details are masked.** Results may go to a cloud model, depending on the client, so
  phone numbers, emails and card numbers come back as `[phone]`, `[email]` and `[card number]`.
  Each lead includes its CRM `url` for the real details.
- **One tool writes, and it's labelled.** `log_activity` goes through the same `LogActivity`
  action as the lead page (stage changes can't be logged by hand). Every entry is tagged
  `{"via": "mcp"}`, so the timeline shows what an assistant wrote.
- **Tokens are scoped, hashed and revocable.** HTTP tokens carry only the `mcp` ability (they
  can't call anything else), are stored as hashes, show when they were last used, and can be
  revoked in one click. Calls are rate-limited per user (60 a minute).

## Connecting Claude Desktop

Claude Desktop's config file lives at:

- Windows: `%APPDATA%\Claude\claude_desktop_config.json`
- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`

Restart Claude Desktop after editing it.

### Option A: HTTP with a token (recommended)

1. In Showroom, go to **Settings → MCP access** and create a token. Copy it; it's shown once.
2. Add this to the config. The page shows it pre-filled with your token.
   [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) bridges Claude Desktop's stdio to
   the HTTP endpoint.

```json
{
    "mcpServers": {
        "showroom": {
            "command": "npx",
            "args": [
                "mcp-remote",
                "http://localhost/mcp",
                "--header",
                "Authorization:Bearer ${SHOWROOM_TOKEN}"
            ],
            "env": { "SHOWROOM_TOKEN": "1|your-token-here" }
        }
    }
}
```

The token sits in `env` rather than in `args` because Windows mangles spaces inside arguments.

### Option B: stdio, no token (local development)

Claude Desktop launches the server inside the Sail container and talks to it over stdin and
stdout. There's no HTTP request to carry a token, so you choose the user with `--user`. Anyone who
can run `docker compose exec` already has the whole database, so this adds no new access.

```json
{
    "mcpServers": {
        "showroom": {
            "command": "docker",
            "args": [
                "compose",
                "--project-directory",
                "C:\\path\\to\\showroom-ai",
                "exec",
                "-T",
                "-u",
                "sail",
                "laravel.test",
                "php",
                "artisan",
                "showroom:mcp",
                "--user=dana@northgate.test"
            ]
        }
    }
}
```

Use `marcus@northgate.test` instead to see the server through a sales rep's eyes.

## Trying it without Claude

Raw JSON-RPC over stdio:

```bash
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"cli","version":"1"}}}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"pipeline_summary","arguments":{}}}' \
  | docker compose exec -T -u sail laravel.test php artisan showroom:mcp --user=dana@northgate.test
```

Or over HTTP with a token:

```bash
curl http://localhost/mcp \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" -H "MCP-Protocol-Version: 2025-06-18" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

The [MCP Inspector](https://github.com/modelcontextprotocol/inspector) (`npx
@modelcontextprotocol/inspector`) also works against `http://localhost/mcp`, using the same
Authorization header.

## Where the code is

- `app/Mcp/Servers/ShowroomServer.php`: the server and its instructions to the model.
- `app/Mcp/Tools/*`: one class per tool; `app/Mcp/Concerns/ActsForStaff.php` holds the shared
  rules.
- `app/Mcp/Prompts/DailyLeadReview.php`, `app/Mcp/Resources/*`.
- `routes/ai.php`: the HTTP route (Sanctum, the `mcp` ability, rate limit) and the local server.
- `app/Console/Commands/StartMcpServer.php`: `showroom:mcp --user=…`.
- `tests/Feature/Mcp/*`: every tool, visibility, masking, token abilities, and revocation.
