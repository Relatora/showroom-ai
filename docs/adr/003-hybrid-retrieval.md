# ADR-003: Use hybrid retrieval (vectors + full-text) fused with RRF

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

Dealership questions mix two very different kinds of query:

- Fuzzy intent: _"something safe for a new driver that's good in snow"_. Embeddings handle
  this well.
- Exact tokens: a VIN, a stock number, a trim like `XLE`, a policy number like `F-204`.
  Embeddings are bad at these. They tend to blur short codes into similar-looking neighbours.

## Decision

Run two searches for every query and merge them with **Reciprocal Rank Fusion**:

1. pgvector cosine distance over the chunk embeddings.
2. PostgreSQL full-text search (`websearch_to_tsquery` + `ts_rank_cd`) over the same chunks.

Each result gets `1 / (k + rank)` from each list it appears in (`k = 60`), and we sort by the sum.
RRF only looks at rank positions, so we don't have to normalise two scores that live on completely
different scales.

## Consequences

- Better recall on both kinds of query, measured by `php artisan showroom:eval`.
- Two queries instead of one. At our scale that's a few milliseconds.
- `k` and the per-list limits are tunables, and the eval tells us if a change helps.

## Alternatives considered

- **Vectors only:** simpler, but it misses exact codes. The eval set has questions that prove it.
- **Weighted score blending:** needs score normalisation and hand-tuned weights. RRF is more robust.
- **A cross-encoder re-ranker:** better precision, but another model to run on an 8 GB GPU.
  Worth revisiting later.
