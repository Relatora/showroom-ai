# Demo script

A two-minute walkthrough, for a video or a live call. Timings are a guide. On a CPU-only
machine, start the Ask question before you start talking about it, because the answer takes
30 to 60 seconds.

**Before you start:** `sail up -d`, `npm run dev`, Ollama running, logged out, dark mode,
browser at 1440×900. Ask one warm-up question so the model is already loaded.

---

### 0:00 The front door (15 s)

Open http://localhost.

> "Showroom AI is a CRM for a car dealership with an assistant built in. Everything you're
> about to see runs on this one machine: the database, the queues and the language model. No
> API keys, no cloud bill."

Scroll once so the Ask demo starts streaming, then click **Try the demo**.

### 0:15 A rep's day (20 s)

Log in as `marcus@northgate.test`.

> "Marcus is a sales rep. His dashboard is his day: who to call first, his appointments and
> his pipeline. He only sees his own leads. That rule matters, because everything AI in this
> app goes through it too."

Open **Leads** and drag one card from _Contacted_ to _Appointment set_.

### 0:35 Ask Showroom (35 s)

Open **Ask Showroom** and ask:

> _Can a customer take a car home overnight?_

While it streams:

> "This searches three ways at once: by meaning, by words and by exact codes like stock
> numbers. It merges the results, and only then hands five passages to the model. The answer
> streams back, and every claim is numbered."

Click citation **1**. The policy page opens with the passage highlighted.

> "Every answer shows its source. If the documents don't cover a question, it says so."

### 1:10 The lead assistant (25 s)

Open a hot lead with a budget in its notes, then click **Find matches**.

> "On a lead, it shortlists available stock within budget in SQL first. A small model is bad
> at comparing prices, so it doesn't get to. Then it picks three and explains why. Stock
> numbers it makes up are thrown away."

Click **Draft follow-up**.

> "And it drafts the next email from the timeline. It's a draft. A person always presses
> send."

### 1:35 Bring your own AI (20 s)

Log out, log in as `dana@northgate.test` (manager), and open **Settings → MCP access**.

> "Showroom is also an MCP server, so Claude Desktop or any agent can work the CRM in plain
> language, as the person who owns the token. The token opens only this endpoint and revokes
> in one click. The AI client can't see anything the person couldn't."

If Claude Desktop is connected, ask it: _"Which hot leads haven't been contacted in 3 days,
and what stock fits them?"_

### 1:55 Close (5 s)

> "Laravel, Vue, Postgres with pgvector, and a local model. The code, the decisions and the
> eval are all in the repo."

---

## If something goes wrong live

- **The answer is slow:** say so. "This is a 7B model on a CPU. On a GPU it's a few
  seconds." Then talk through the sources, which arrive first.
- **Ollama is down:** the app shows a friendly error rather than breaking. That's worth
  showing too.
- **Matching returns nothing:** pick a lead with a stated budget over $30,000.
