# ADR 0002: Shared database, tenant_id on every row, enforced by Postgres row-level security

Status: accepted (13 Nov 2026)

## Context

Each agency (tenant) must never see another agency's freelancers, clients, documents or users. A single bug in one query must not leak data. Budget rules out one server or database per agency.

## Options

1. Database per tenant: strongest isolation, but migrations, backups and cost multiply per agency.
2. Schema per tenant: similar operational cost, awkward with many agencies.
3. **Shared tables with `tenant_id` (chosen)**, protected by two independent layers.

## Decision

**Layer 1, application.** Every tenant-owned model uses `App\Tenancy\BelongsToTenant`:
- global scope `where tenant_id = current agency`; with no agency set the query returns **nothing** (fails closed);
- `tenant_id` is filled from the current agency on create; creating for another agency or changing `tenant_id` throws.

**Layer 2, database.** Every table with `tenant_id` has `ENABLE` + `FORCE ROW LEVEL SECURITY` and the policy
`tenant_id = NULLIF(current_setting('app.tenant_id', true), '')::uuid` for reads **and** writes.
`App\Tenancy\TenantContext` sets `app.tenant_id` per request (from the host name) and per queued job (from the job payload), and clears it afterwards.

**Three Postgres roles:**

| Role | Used by | Rights |
|---|---|---|
| `sp_owner` | migrations only | owns tables; RLS still forced |
| `sp_app` | web app and queue workers | CRUD on tenant tables through RLS; read-only on `tenants`, `tenant_domains`, `roles`, `permissions`; insert-only on `audit_logs`; **no access** to `platform_admins` |
| `sp_platform` | super-admin panel, provisioning | `BYPASSRLS`, only on `admin.<base domain>` |

**Agency resolution.** `ResolveTenant` maps the host name to an agency (`{slug}.<base domain>` or a verified custom domain). Unknown or suspended agencies return 404. Sessions use host-only cookies, and users are loaded through both layers, so a session from one agency is a guest on another.

**Users belong to one agency.** The same email in two agencies is two accounts. Roles are spatie/laravel-permission "teams" keyed on `tenant_id`.

## Guard rails (automated)

- `tests/Feature/Tenancy/RowLevelSecurityTest` uses raw SQL to prove Postgres blocks cross-agency reads, inserts, updates, and audit tampering.
- The same test fails if any table with `tenant_id` is missing forced RLS.
- `php artisan platform:check-db` (run after every deploy) fails if the app role is superuser, has BYPASSRLS, owns tables, or can read `platform_admins`.

## Rules for new code

1. New tenant table: `tenant_id` FK + `RowLevelSecurity::protect()` + grants, in the same migration.
2. New tenant model: `use BelongsToTenant`.
3. Never use `withoutGlobalScopes()` on tenant models outside the platform panel.
4. Raw SQL is fine; RLS still applies.
5. Long-running processes (Octane) must keep `ResolveTenant` resetting the context; it does so in `finally`.
