# AI workflow

How a team should use AI assistants on a codebase like this one, both the coding assistants we
work with and the AI features we ship. It's short on purpose. A policy nobody remembers is no
policy at all.

The one-line version: **AI makes us faster. It doesn't change who is accountable.** The person
who opens the PR owns every line in it, however it was written.

## 1. Coding with an assistant

Use one. Most of us are faster with an assistant for scaffolding, tests, refactors, reading
unfamiliar code and first drafts of docs. The rules are about keeping the quality bar where it
is.

**You review it before anyone else does.** If you couldn't explain a line in review, it
doesn't go in the PR. "The assistant wrote it" is not an answer to a review question.

**Tests are not optional, and they're not the assistant's to grade.** Generated code comes
with a test that fails without the change. Read generated tests closely. A common failure is a
test that asserts whatever the code happens to do, rather than what it should do.

**Watch for the usual failure modes:**

- APIs that don't exist, or exist in a different version. Check against the installed package
  (`vendor/`, `node_modules/`), not the assistant's memory.
- Authorization quietly dropped. A query that skips `visibleTo()` or a policy check is the most
  expensive bug in this codebase. Reviewers check it on every PR.
- Confident comments that describe what the code should do rather than what it does.
- Plausible but wrong domain details, like a made-up policy number or a price rule nobody asked
  for.

**Keep PRs small.** It's easy to generate a 2,000-line PR. Nobody can review one. The size
guidance in [CONTRIBUTING.md](CONTRIBUTING.md) applies whatever wrote the code.

## 2. What never goes into a prompt

This applies to any assistant or model that isn't running on our own hardware.

- **Customer data:** names with contact details, phone numbers, emails, addresses, licence
  numbers, credit applications. Use the seeded demo data, or mask it first.
- **Secrets:** `.env` files, API keys, tokens, database dumps, private keys.
- **Anything under NDA** or a customer contract.

If you're not sure, it doesn't go in. Ask first.

## 3. AI features in the product

The same principles, built into the code so nobody has to remember them.

| Principle                             | How it's enforced                                                                                      |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| The model sees only what the user may | Retrieval and every MCP tool scope through `visibleTo()` and the policies, inside the query            |
| Personal data stays out of prompts    | `PiiMasker` runs before text is embedded or sent to a model, and again on MCP output                   |
| Answers show their work               | Every answer cites numbered passages. "I don't know" is an acceptable answer                           |
| Model output is data, not code        | Rendered as text (`AnswerText.vue`), never as HTML. Never executed, never sent to a customer by itself |
| A person presses send                 | Follow-up drafts are drafts. Nothing reaches a customer without a click                                |
| Invented facts are thrown away        | Vehicle matching only accepts stock numbers from the shortlist it gave the model                       |
| Quality is measured                   | `showroom:eval` scores retrieval. Changes to chunking or ranking show a before and after               |
| Tests never call a real model         | `Embeddings::fake()` and agent fakes. The suite runs offline and gives the same result every time      |
| Usage is bounded                      | Per-user rate limits on the assistant (10/min) and MCP (60/min), plus server-side time limits          |

## 4. Agents and MCP: least privilege

When an AI client acts in the CRM through MCP, treat it like a new employee on day one.

- **It acts as one person.** A token belongs to a user, and every tool runs with that user's
  permissions. There's no "service account" that sees everything.
- **Tokens open one door.** MCP tokens carry only the `mcp` ability, so they can't call the
  rest of the app. They're shown once and revocable in one click.
- **Read by default.** Five of the six tools only read. The one write tool, `log_activity`,
  goes through the same action and policy as the UI and labels what it writes (`via: mcp`).
- **Assume the client's model is in the cloud.** Even though our own model is local, an MCP
  client may not be. Contact details leave the server masked.
- **New write tools need a review from two people** and a written reason in the PR for why the
  agent needs it.

## 5. When the model is wrong

It will be. A 7B model running locally gets numeric comparisons wrong and sometimes over-reads
a passage. The answer is design, not hope:

- Do arithmetic and filtering in SQL, and let the model choose and explain.
- Keep prompts short and specific. A worked example in a prompt can make a small model parrot
  the example (we hit exactly that while tuning the assistant).
- When an answer is wrong in a way that matters, add it to `evals/questions.json` so it stays
  fixed.
- Tell users plainly that answers can be wrong. The assistant's input box says so.

## 6. Changing this document

Anyone can propose a change in a PR. Changes to sections 2 and 4 need the engineering
manager's review.
