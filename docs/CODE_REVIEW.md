# Code review guide

Review is how we share context and keep the bar consistent. It isn't a gate to get past. These
are the things I look for, roughly in priority order.

## 1. Does it do the right thing?

- Does it solve the problem in the linked issue, and only that problem?
- Edge cases: empty lists, a lead with no customer email, a vehicle that just sold, a rep with
  no leads, a document that fails to parse.
- What happens when the model is slow, down or returns nonsense?

## 2. Is it safe?

- **Authorization:** every new query that returns CRM data goes through a policy or a scoped
  query. That includes retrieval and MCP tools. "The UI hides the button" is not authorization.
- **Mass assignment:** validated input only (`$request->validated()`), never `$request->all()`.
- **AI inputs:** customer phone numbers and emails pass through `PiiMasker` before any prompt.
- **AI outputs:** model text is rendered as text, never as HTML. Nothing the model says is
  executed or sent to a customer without a person clicking a button.

## 3. Will the next person understand it?

- Names say what things are. Comments say _why_, not _what_.
- Business rules live in one place: an action, a model method or a policy, not repeated in
  three controllers.
- Vue components stay focused. If a page passes 300 lines, something wants extracting.

## 4. Is it tested?

- A feature test that would have failed before the change.
- No real model calls in tests (`MyAgent::fake()`, `Embeddings::fake()`).

## 5. Does it perform?

- N+1 queries: eager load what the page uses. Laravel's `preventLazyLoading` is on outside
  production and will shout at you.
- Anything slow (embedding, parsing, LLM calls) runs on the queue, not in the request.

## Tone

- Ask questions instead of issuing orders: "What happens if…?" rather than "This is wrong."
- Label nitpicks as `nit:` so the author knows they're optional.
- Approve with small comments rather than blocking on them.
- If a thread passes three back-and-forths, talk it through instead.
