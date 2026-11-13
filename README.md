# Showroom AI

A CRM for car dealerships with an AI sales assistant built in. It answers questions about your
inventory, policies and customers with sources cited, and it exposes the CRM to AI clients
through an MCP server. Everything runs locally and costs nothing.

> 🚧 Work in progress. The full README lands with v1.0.0. See [the design doc](docs/DESIGN.md)
> and the [milestones](https://github.com/Relatora/showroom-ai/milestones) for where things are.

## Stack

Laravel 13 · Inertia 3 · Vue 3 + TypeScript · Tailwind 4 · PostgreSQL + pgvector · Redis +
Horizon · RustFS (S3-compatible) · Ollama · Prism · Laravel MCP

## Running it locally

You need Docker, Node 22+ and [Ollama](https://ollama.com).

```bash
cp .env.example .env
docker run --rm -v "$(pwd):/app" -w /app composer:2 composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
npm install && npm run dev
```

Then open http://localhost.
