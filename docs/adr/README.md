# Architecture decision records

Short write-ups of decisions that shape the codebase: what we chose, why, and what it costs us.
If you're about to ask "why is it done this way?", the answer is probably here. If it isn't,
that's a sign we owe an ADR.

| #                                               | Decision                                               | Status   |
| ----------------------------------------------- | ------------------------------------------------------ | -------- |
| [001](001-local-llm-via-ollama.md)              | Run the language models locally with Ollama            | Accepted |
| [002](002-pgvector-over-a-vector-database.md)   | Store embeddings in PostgreSQL with pgvector           | Accepted |
| [003](003-hybrid-retrieval.md)                  | Hybrid retrieval (vectors + full-text) fused with RRF  | Accepted |
| [004](004-s3-compatible-storage-with-rustfs.md) | RustFS locally as an S3-compatible stand-in for AWS S3 | Accepted |
| [005](005-inertia-over-a-separate-spa.md)       | Inertia + Vue instead of a separate SPA and API        | Accepted |

New ADRs start from [the template](000-template.md). Never edit an accepted ADR's decision.
Write a new one that supersedes it, so the history stays readable.
