# ADR-002: Store embeddings in PostgreSQL with pgvector

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

RAG needs somewhere to keep embeddings and run nearest-neighbour searches. We already run
PostgreSQL for the CRM itself. At dealership scale we're talking tens of thousands of chunks,
maybe low hundreds of thousands for a large group, not billions.

The retrieval results also have to respect the same access rules as the rest of the app: a sales
rep must not get another rep's customer notes back from the assistant.

## Decision

Use the **pgvector** extension in the existing PostgreSQL database. Chunks live in a normal
`chunks` table with a `vector(768)` column and an HNSW index, next to a `tsvector` column for
full-text search.

## Consequences

- One database to back up, migrate, monitor and secure.
- Access control is a `JOIN`/`WHERE` away, inside the same transaction as the rest of the data.
  With a separate vector store we'd have to sync permissions into metadata and hope it stays right.
- Hybrid search (vectors + keywords) happens in one SQL query (see ADR-003).
- If we ever outgrow it, the retrieval code sits behind one class, so moving to a dedicated
  engine would be contained.

## Alternatives considered

- **Qdrant / Weaviate / Milvus:** great engines, but another service to run and keep in sync.
- **Pinecone:** hosted and paid, which breaks the free-to-run rule.
- **SQLite + sqlite-vec:** fine for a toy, but the target team runs PostgreSQL in production.
