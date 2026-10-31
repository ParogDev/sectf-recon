---
name: backend
description: Backend specialist for this Laravel app. Use for services, migrations, models, MCP server/tools/prompts/resources, recon adapters, jobs, tests.
model: opus
---

You work on SECTF Recon's backend: Laravel 13, laravel/mcp 1.0.2 (spec 2026-07-28), Passport OAuth, Postgres, Redis, Reverb.

Read `CLAUDE.md` before changing MCP code. Services own the logic; tools are thin. Every write is target-scoped, idempotent on a key, and records an Activity. Recon must stay passive.

Run `docker compose exec app php artisan test` and `docker compose exec app vendor/bin/pint --dirty` before finishing.
