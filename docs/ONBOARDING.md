# Onboarding

Welcome. This is what I'd hand a new engineer on their first day. By the end of it you should
have the app running, know where the important code lives, and have a small first ticket
merged.

## Day one: get it running

You need Docker Desktop, Node 22+, Git and [Ollama](https://ollama.com). PHP and Composer are
not needed on your machine. Everything PHP runs in the Sail containers.

```bash
ollama pull nomic-embed-text   # embeddings, ~275 MB
ollama pull qwen2.5:7b         # chat, ~4.7 GB
```

```bash
git clone https://github.com/Relatora/showroom-ai.git && cd showroom-ai
cp .env.example .env
docker run --rm -v "$(pwd):/app" -w /app composer:2 composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan showroom:storage-init
./vendor/bin/sail artisan showroom:ai-check     # one embedding and one chat round trip
./vendor/bin/sail artisan showroom:index        # index the CRM and the policy documents
npm install && npm run dev
```

Open http://localhost and log in as `dana@northgate.test` (manager) or
`marcus@northgate.test` (rep). The password is `password`. Log in as both: what each one sees
is the most important thing to understand about this app.

### When something goes wrong

| Symptom                                          | Fix                                                                                                    |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------ |
| `showroom:ai-check` can't reach Ollama           | Ollama must be running on the host. The containers reach it at `host.docker.internal:11434`            |
| Ollama crashes on the first request (0xc0000409) | An old GPU driver. Set `OLLAMA_NUM_GPU=0` in `.env` to run on the CPU, or update the driver            |
| Pages and tests are slow on Windows or macOS     | Copy `compose.override.example.yaml` to `compose.override.yaml`. It keeps `vendor/` in a Docker volume |
| `Permission denied` writing to `storage/`        | `docker compose exec laravel.test chown -R sail:sail storage bootstrap/cache`                          |
| The assistant says it has no sources             | Run `showroom:index` again, and check Horizon (http://localhost/horizon) for failed jobs               |
| `sail` says the OS isn't supported (Git Bash)    | Use `docker compose exec -u sail laravel.test php artisan …` instead                                   |

## Day one: read the code in this order

Each step builds on the one before. Budget about two hours.

1. **[DESIGN.md](DESIGN.md)**, sections 1 to 3. What we're building and the domain model.
2. **The access rule.** `Lead::visibleTo()` in `app/Models/Lead.php` and `app/Policies/`.
   Everything else depends on this: pages, retrieval and MCP tools all scope through it.
3. **One page end to end.** `routes/web.php` → `LeadController@show` → `LeadResource` →
   `resources/js/pages/leads/Show.vue`. That's the shape of every page: a thin controller, a
   resource for the props, and a Vue page that receives typed props through Inertia.
4. **One write.** `app/Actions/Leads/` and `LeadStageController`. Business rules live in
   actions, so the web app and the MCP tools share them.
5. **Indexing.** `app/Services/Knowledge/Indexer.php`, then `Chunker`, `PiiMasker` and
   `Embedder`. Then `app/Observers/` to see how CRM changes get re-indexed.
6. **Retrieval.** `HybridRetriever.php` and [ADR-003](adr/003-hybrid-retrieval.md). Run
   `sail artisan showroom:eval` and read `evals/results.md`.
7. **The assistant.** `app/Ai/Agents/ShowroomAssistant.php`, `AssistantController@ask` (the
   server-sent events), then `resources/js/composables/useEventStream.ts`.
8. **MCP.** `routes/ai.php`, `app/Mcp/Servers/ShowroomServer.php`,
   `app/Mcp/Concerns/ActsForStaff.php`, then [MCP.md](MCP.md).
9. **How we work.** [CONTRIBUTING.md](../CONTRIBUTING.md), [CODE_REVIEW.md](CODE_REVIEW.md)
   and [AI_WORKFLOW.md](../AI_WORKFLOW.md).

## Where things live

```
app/
  Actions/          business operations shared by the web app and MCP (e.g. log an activity)
  Ai/Agents/        prompts and settings for each model call
  Http/Controllers/ thin controllers, grouped by area
  Mcp/              the MCP server: tools, prompts, resources
  Models/           Eloquent models, scopes such as visibleTo() and open()
  Observers/        re-index CRM records when they change
  Policies/         who may view or change what
  Services/         Knowledge (index + search) and Leads (vehicle matching)
resources/js/
  pages/            one Vue page per Inertia route
  components/       crm/, assistant/, landing/ and the ui/ primitives
  composables/      useEventStream, useThemeTransition, useCrm, ...
database/seeders/Demo/  the made-up dealership, its people and its policies
evals/              the retrieval question set and the committed results
docs/adr/           decisions, and why
```

## Your first ticket

Pick one. Each takes an afternoon and touches the full stack.

- **Add a `get_vehicle` MCP tool** that takes a stock number. Copy the shape of
  `FindMatchingVehicles`, write the test first in `tests/Feature/Mcp/ShowroomToolsTest.php`.
- **Add three eval questions** for the delivery checklist, run `showroom:eval --write`, and
  explain any change in the PR.
- **Show "last indexed" on a vehicle page**, from the newest chunk for that vehicle.

Open an issue first, branch as `feat/<issue>-short-name`, and run `npm run ci` before you push.

## Who to ask

In a real team this section names people. Here: open an issue with the `question` label, or
read the ADRs. Most "why is it like this?" questions are answered there.
