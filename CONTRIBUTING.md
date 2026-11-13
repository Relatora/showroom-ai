# Contributing to Showroom AI

Thanks for taking a look. This is how work moves through the repo. It's the same process I'd
want on a team of five or six engineers, just applied to a smaller codebase.

## The flow

1. **Every change starts as an issue.** It says what problem we're solving and what "done" looks
   like as a checklist. If it's too big for one PR, split it.
2. **Branch from `main`** using `type/<issue>-short-name`, e.g. `feat/8-lead-board`,
   `fix/31-timeline-order`, `docs/19-readme`.
3. **Commit in small steps.** One idea per commit. Subject line in the imperative ("Add lead
   board"), under ~70 characters, then a body that explains _why_ if it isn't obvious.
4. **Open a PR early** and fill in the template. Link the issue with `Closes #N`.
5. **The quality gate must pass** (see below) before anything merges.
6. **Squash-merge** so `main` reads as one commit per change, then delete the branch.

## The quality gate

GitHub Actions isn't enabled for this repo, so the gate runs locally. One command does it:

```bash
npm run ci          # or ./scripts/ci.sh, add --php or --js to run half
```

| Step                | Tool                  | Why                                            |
| ------------------- | --------------------- | ---------------------------------------------- |
| PHP style           | Pint (`--test`)       | No style discussions in review                 |
| PHP static analysis | Larastan (level 7)    | Catches the bugs tests don't think to look for |
| Tests               | Pest                  | Behaviour, policies, jobs, MCP tools           |
| Frontend lint       | Vite+ (Oxlint/format) | Consistent Vue and TypeScript                  |
| Types               | vue-tsc               | Props and route helpers stay honest            |
| Build               | Vite                  | The production bundle still builds             |

To have it run automatically before every push:

```bash
git config core.hooksPath .githooks
```

## Tests

- Feature tests for anything a user or an AI client can do. Unit tests for pure logic
  (chunking, rank fusion, PII masking).
- **Never call a real model in tests.** Use the AI SDK fakes (`MyAgent::fake()`, `Embeddings::fake()`) so tests are fast and
  deterministic.
- Policies get tested from both sides: the person who should get in, and the one who shouldn't.

## Using AI assistants on this codebase

Read [`AI_WORKFLOW.md`](AI_WORKFLOW.md) first. Short version: you own every line you commit, AI
output gets the same review as anyone else's, and customer data never goes into a prompt.

## Writing things down

If you make a decision that someone will later ask "why did we do it this way?", write an ADR in
[`docs/adr`](docs/adr) using the template. It takes ten minutes and saves the meeting.
