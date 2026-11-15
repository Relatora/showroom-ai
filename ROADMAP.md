# Roadmap

v1.0.0 is a complete, working product for one dealership on one machine. This is what I'd
plan next, written the way I'd plan a quarter with a team: outcomes first, then the work,
then how we'd know it worked. Each item would become a milestone with its own issues.

## Where we are (v1.0.0)

| Release | Theme         | What shipped                                                                 |
| ------- | ------------- | ---------------------------------------------------------------------------- |
| v0.1.0  | Foundation    | Laravel 13 + Vue 3.5 on Sail, storage, local models, quality gate, ADRs      |
| v0.2.0  | CRM core      | Leads board and timeline, inventory, appointments, dashboard, roles          |
| v0.3.0  | Indexing      | Document upload, chunking, embeddings, PII masking, observers                |
| v0.4.0  | The assistant | Hybrid retrieval, Ask Showroom, vehicle matching, follow-up drafts, the eval |
| v0.5.0  | MCP           | Six tools, a prompt, policy resources, scoped tokens, stdio and HTTP         |
| v1.0.0  | Launch        | Landing page, docs, accessibility and mobile pass                            |

## Next quarter

### 1. Trust the answers more (highest priority)

**Outcome:** a rep can use an answer with a customer without double-checking it every time.

- Grow the eval from 35 to 100 questions, written with the sales manager from real floor
  questions. Track it per release in `evals/results.md`.
- Add an answer-level eval, not just retrieval: does the answer say what the passage says?
  Start with 30 questions graded by a person, then try a model as a second grader.
- A thumbs-down button on answers that files the question into a review queue.
- **We'd know it worked when:** hybrid hit@5 stays at or above 0.95 on the bigger set, and
  thumbs-down drops below 1 in 20 answers.

### 2. Make it fast enough to feel instant

**Outcome:** the first words of an answer in under 2 seconds.

- Run the model on a GPU (the driver crash that forced CPU mode is a known, fixable issue).
- Cache embeddings of repeated questions, and keep the model loaded between requests.
- Measure: time to sources, time to first token and total time are already in the `done`
  event. Log them and chart the 95th percentile.
- **We'd know it worked when:** p95 time to first token is under 2 seconds on the GPU.

### 3. More than one dealership

**Outcome:** a dealer group can run several stores from one install.

- A `dealership_id` on every tenant table, enforced by a global scope plus a test that fails
  if a new model forgets it. The visibility rules already go through one scope, which makes
  this tractable.
- Per-store policies in the knowledge base, with group-wide ones shared.
- An ADR first: single database with a tenant column, versus a schema per store.
- **We'd know it worked when:** a cross-tenant test suite proves no query leaks across stores.

### 4. Production readiness

**Outcome:** it could run somewhere other than a laptop.

- Deploy target: a single VM with Docker Compose first, then managed Postgres and S3. Storage
  already speaks S3, so that move is configuration (ADR-004).
- CI on a runner that works (GitHub Actions is billing-locked on this account today).
- Error tracking, structured logs, and a health check covering Ollama, Postgres and Redis.
- Backups for Postgres and the document bucket, with a restore drill.

## Later, not yet

These are good ideas that aren't worth doing before the work above.

- **Real email sending** for follow-ups. Today they're drafts on purpose, and sending needs
  consent tracking and unsubscribe handling first.
- **Telephony and SMS** integrations that log calls to the timeline automatically.
- **A mobile layout tuned for the lot**: big buttons, offline notes, VIN scanning.
- **Fine-tuning** a model on dealership language. Retrieval quality is the bottleneck, not the
  model's vocabulary, so this waits until the eval says otherwise.

## How we'd run it

- Two-week iterations. Each starts from this roadmap and ends with a release and notes.
- One theme at a time owns most of the capacity. About 20% stays free for bugs and
  maintenance.
- Every theme has its "we'd know it worked when" line. If we can't measure it, we write down
  why we're doing it anyway.
