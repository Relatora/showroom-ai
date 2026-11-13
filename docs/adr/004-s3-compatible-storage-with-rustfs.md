# ADR-004: Use RustFS locally as an S3-compatible stand-in for AWS S3

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

Uploaded documents (spec sheets, warranty booklets, finance policies) need to live in object
storage. In production at most dealership SaaS companies that means **AWS S3**. But S3 is a paid
service, and this project must run for free on a laptop.

The usual answer used to be MinIO. Its community edition was archived in 2026 and no longer
publishes Docker images, so it's not something to build on.

## Decision

Run **RustFS** (`rustfs/rustfs`) in Docker Compose, using the stub that ships with Laravel Sail.
RustFS speaks the S3 API, so the app uses Laravel's normal `s3` filesystem driver and the AWS SDK,
pointed at `http://rustfs:9000` with path-style URLs.

All document code goes through `Storage::disk('documents')`. Nothing in the app knows or cares
that it isn't talking to AWS.

## Consequences

- $0 to run, no AWS account needed, works offline.
- Moving to real S3 is configuration only:

    ```dotenv
    DOCUMENTS_DISK_ENDPOINT=          # empty = AWS default endpoint
    AWS_USE_PATH_STYLE_ENDPOINT=false
    AWS_ACCESS_KEY_ID=...
    AWS_SECRET_ACCESS_KEY=...
    AWS_DEFAULT_REGION=ca-central-1
    AWS_BUCKET=showroom-documents
    ```

- We don't exercise S3-only features (IAM policies, lifecycle rules, presigned POST edge cases).
  Those would be tested against a real bucket in a staging account.

## Alternatives considered

- **MinIO:** archived upstream, no maintained images.
- **Garage / SeaweedFS:** solid options, but RustFS is the one Sail already supports, so it's
  less custom config for the next developer.
- **LocalStack:** emulates many AWS services, but the free tier now requires an account token.
- **The `local` disk:** simplest, but it hides the S3 integration we'd actually ship.
