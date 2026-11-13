# ADR-001: Run the language models locally with Ollama

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

Showroom AI needs two kinds of model: an embedding model for retrieval and a chat model for
answers, matching and drafting. The project has a hard rule that it costs nothing to run, and
anyone cloning the repo should be able to try it without signing up for an API key.

There's also a real-world angle. Dealership CRMs hold names, phone numbers, trade-in values and
credit notes. Plenty of dealer groups would rather that data never leave their network.

## Decision

Use **Ollama** on the host machine, with `nomic-embed-text` for embeddings and an 8B instruct
model for chat. The app talks to it through Laravel's first-party **AI SDK** (`laravel/ai`), so the
provider is a config value rather than something baked into the code. The SDK also gives us
agents with tools, streaming, embeddings, pgvector query helpers and proper test fakes.

Inside Docker the app reaches Ollama at `http://host.docker.internal:11434`. Running Ollama on
the host rather than in a container keeps GPU access simple on Windows and macOS. There's an
`ollama` compose profile for people who'd rather keep everything in containers (CPU only, slower).

## Consequences

- Zero running cost and no data leaves the machine.
- 8B models are noticeably weaker than hosted frontier models. We compensate with tight prompts,
  required citations, an "I don't know" path and a retrieval eval that keeps us honest.
- Swapping to a hosted model later is a `.env` change (`AI_PROVIDER`, `AI_CHAT_MODEL`),
  which is exactly the kind of decision a team should be able to make without a rewrite.
  The SDK even supports failover between providers.

## Alternatives considered

- **Hosted APIs (OpenAI, Anthropic, Bedrock):** best quality, but they cost money and need keys.
- **llama.cpp directly:** more control, more setup. Ollama wraps it with a stable HTTP API.
- **vLLM:** built for serving at scale on Linux GPUs, overkill for a single laptop.
