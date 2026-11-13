# ADR-005: Build the UI with Inertia + Vue instead of a separate SPA and API

- **Status:** Accepted
- **Date:** 2026-11-13

## Context

The CRM is an internal tool used by logged-in staff. We want a fast, app-like UI with Vue and
TypeScript, but we don't have a second client (like a public API or native app) that would
justify maintaining a separate JSON API for the screens.

## Decision

Use **Inertia 3** with **Vue 3 + TypeScript**, starting from Laravel's official Vue starter kit.
Controllers return Inertia pages with props, routing stays in Laravel, and **Wayfinder** generates
typed route helpers so the frontend never hard-codes a URL.

The MCP server is the one place we expose data to non-browser clients, and it has
its own, deliberately small surface.

## Consequences

- One codebase, one auth system (session cookies), one set of policies.
- No API versioning or token juggling for the main UI.
- If a mobile app becomes a real requirement, we'd add an API for the endpoints it needs, rather
  than for every screen up front.

## Alternatives considered

- **Nuxt or a Vite SPA + Laravel API:** more moving parts and duplicated validation and auth.
- **Livewire:** excellent, but the target team works in Vue + TypeScript.
