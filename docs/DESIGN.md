# Showroom AI — Design & Build Plan

> Status: **Approved** · 2026-11-13

## 1. What it is

Showroom AI is a small CRM for a car dealership with an AI sales assistant built in.
Sales reps manage leads, customers, inventory and test drives. The assistant answers
questions over the dealership's own documents and data (RAG), with sources cited, and the
whole CRM is exposed to AI clients such as Claude Desktop through an MCP server.

**Who it is for:** hiring managers for a PHP/Laravel engineering leadership role on a
dealership CRM. The repo must show three things:

1. Strong hands-on Laravel + Vue/TypeScript + PostgreSQL + Redis.
2. Practical, safe AI features (RAG, MCP), not a demo toy.
3. How a lead engineer runs a codebase: decisions written down, tests, review rules,
   an AI-usage policy for the team, and a clear roadmap.

**Hard rule: $0 to run.** Everything runs locally. No paid APIs, no cloud accounts.

## 2. Stack

| Concern       | Choice                                              | Notes                                                                                                                    |
| ------------- | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Backend       | Laravel 13, PHP 8.4                                 | Runs in Docker via **Laravel Sail**, so PHP doesn't need installing on Windows                                           |
| Frontend      | Inertia 3 + Vue 3.5 + TypeScript + Tailwind 4       | Laravel's official Vue starter kit                                                                                       |
| Database      | PostgreSQL 17 + **pgvector**                        | Holds app data, embeddings and full-text indexes                                                                         |
| Queue / cache | Redis + **Horizon**                                 | Ingestion and embedding run as queued jobs                                                                               |
| File storage  | **RustFS** in Docker, which speaks the S3 API       | Free and local. AWS S3 is paid, so it is not used. Moving to S3 later is a config change only (ADR-004)                  |
| LLM           | **Ollama**, native on Windows with the RTX 3070 GPU | Chat model `qwen3:8b` or `qwen2.5:7b` (~5 GB VRAM, Q4), picked in Phase 0 by a quick quality and speed test              |
| Embeddings    | Ollama `nomic-embed-text` (768 dimensions)          |                                                                                                                          |
| LLM client    | **Laravel AI SDK** (`laravel/ai`)                   | First-party: agents, tools, streaming, embeddings, pgvector helpers and test fakes. Changing provider is a config change |
| MCP           | **`laravel/mcp`**, the official package             | HTTP transport with Sanctum tokens, plus stdio for Claude Desktop                                                        |
| Quality       | Pest, Larastan, Pint, ESLint, vue-tsc               | One local CI script (`composer ci`). GitHub Actions is locked on this account, so CI runs locally                        |

The Docker containers reach Ollama at `http://host.docker.internal:11434`. A
`docker compose --profile ollama` option is also provided so a reviewer without a GPU
can run everything in containers on CPU (slower).

## 3. Domain model

```
User (role: rep | manager | bdc)
Customer ──< Lead >── User (assigned rep)
   │          │
   │          ├──< Activity   (call, email, note, status change; also a polymorphic timeline)
   │          └──< Appointment (test drive, with Vehicle)
   │
Vehicle (VIN, make, model, year, trim, price, km, status, specs JSON)
Document (uploaded PDF/Markdown: policies, spec sheets, warranty, finance)
Chunk (source: Document | Vehicle | Activity, content, embedding vector(768), tsvector)
AssistantConversation ──< AssistantMessage (with cited chunk ids)
```

Access rules (Laravel Policies): a rep sees their own leads, a manager sees everyone's.
**The same rules filter RAG retrieval and MCP tools.** The assistant can never show a rep
data they couldn't open in the UI.

## 4. RAG pipeline

**Indexing** (queued, observable in Horizon):

1. A document is uploaded and stored in RustFS, then `ExtractText` runs (PDF parser / Markdown).
2. `ChunkText` splits it into ~500-token chunks with overlap, following headings where possible.
3. `EmbedChunks` sends batches to Ollama and stores the vectors.
4. Vehicles and activity notes are indexed automatically by model observers when they change.

**Retrieval:** hybrid search, combining pgvector cosine similarity with PostgreSQL
full-text search through Reciprocal Rank Fusion, then filtered by the access policies. Hybrid
search matters here because exact tokens like VINs, trim names ("XLE") and policy numbers
are found by keywords, while "family car good in snow" is found by vectors.

**Generation:** the prompt must cite sources as `[1]`, `[2]` and say "I don't know" when the
context doesn't contain the answer. Answers stream to the UI. Each citation links to its
document page, vehicle or activity.

**Privacy guardrail:** customer phone numbers and emails are masked before any text reaches
the LLM. Not strictly needed with a local model, but it's the habit the team should have.
Documented in `AI_WORKFLOW.md`.

**Features built on top:**

- _Ask Showroom_: a chat panel with citations.
- _Match inventory_: given a lead's stated needs, find and rank matching vehicles with reasons.
- _Draft follow-up_: write a personal email based on the lead's timeline and matching stock. The rep edits it before sending, and nothing is sent automatically.

## 5. MCP server

Laravel exposes an MCP server (`/mcp` over HTTP with a Sanctum token, plus
`php artisan mcp:start showroom` over stdio).

| Tool                     | Purpose                                                                                       |
| ------------------------ | --------------------------------------------------------------------------------------------- |
| `search_leads`           | Filter by status, rep, last contact, budget                                                   |
| `get_customer_timeline`  | Full activity history for a customer                                                          |
| `find_matching_vehicles` | Hybrid search over inventory                                                                  |
| `search_knowledge_base`  | RAG retrieval over policies and docs, returning cited chunks                                  |
| `log_activity`           | Add a note or call to a lead. This is the only write tool, and the policy check still applies |
| `pipeline_summary`       | Lead counts by stage, stale leads, per-rep load (manager only)                                |

Plus one MCP **prompt** (`daily_lead_review`) and policy documents exposed as MCP
**resources**. Example manager question in Claude Desktop: _"Which hot leads haven't been
contacted in 3 days, and what stock fits them?"_

## 6. Proving it works

- **Pest tests** for policies, ingestion jobs (with the AI SDK fakes), retrieval ranking, and every MCP tool.
- **RAG evaluation:** `php artisan showroom:eval` runs ~25 questions with known correct
  sources and reports hit@5 and MRR. Results are committed, so changes to chunking or
  ranking show up as a before/after score.
- **Seed data:** a made-up dealership with ~60 vehicles, ~150 customers and leads, and
  ~10 policy documents we write ourselves. No real customer data, no real dealer branding.

## 7. Engineering-leadership artifacts

- `docs/adr/` with these ADRs: 001 local LLM via Ollama · 002 pgvector over a separate
  vector database · 003 hybrid retrieval · 004 RustFS as an S3 stand-in · 005 Inertia over a separate SPA.
- `CONTRIBUTING.md`, `.github/pull_request_template.md`, `docs/CODE_REVIEW.md`, `docs/ONBOARDING.md`.
- `AI_WORKFLOW.md`: how a team uses AI assistants safely. Covers review requirements, what
  never goes into a prompt, how agents get access through MCP with least privilege, and
  how AI-written code is tested.
- `ROADMAP.md`: milestones written the way a team plans a quarter.
- README with an architecture diagram (Mermaid), screenshots, and a 2-minute demo video.

## 8. Phases

| Phase            | Deliverable                                                                                                                  | Done when                                                  |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| **0. Setup**     | Ollama + models, Laravel scaffold with Sail (pgvector, Redis, RustFS, Horizon), git repo, CI script, ADR skeletons           | `sail up` works, the home page loads, `composer ci` passes |
| **1. CRM core**  | Migrations, models, policies, seeders; pages: dashboard, leads (list + board), lead detail timeline, inventory, appointments | A rep can work a lead end to end; tests pass               |
| **2. Indexing**  | Document upload, extract/chunk/embed jobs, observers for vehicles and notes                                                  | Uploading a PDF gives searchable chunks visible in Horizon |
| **3. Assistant** | Hybrid retrieval, Ask Showroom chat with citations, Match inventory, Draft follow-up, eval command                           | Eval shows hit@5 ≥ 0.8 on the question set                 |
| **4. MCP**       | Tools, prompt, resources, token auth, tests, Claude Desktop setup guide                                                      | Claude Desktop can answer the manager example above        |
| **5. Polish**    | README, diagrams, leadership docs, roadmap, demo video script                                                                | A stranger can clone and run it in about 10 minutes        |

## 9. Prerequisites and risks

- **Disk space:** Docker images (~6 GB) and models (~11 GB) need room. Fine on the dev machine.
- **8 GB VRAM:** fits one 7–8B chat model plus the embedding model. Larger models would
  run partly on CPU and be slow, so we stay at 8B.
- **Local-model quality:** smaller models make more mistakes. The citation requirement,
  "I don't know" behaviour and the eval command are how we keep that visible and under control.
