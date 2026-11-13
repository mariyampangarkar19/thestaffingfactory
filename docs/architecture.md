# Architecture

## Big picture

```
                 thestafffactory.com (WordPress, stays)          other agencies' sites
                          │  signed POST /api/v1/intake/*                 │
                          ▼                                               ▼
  ┌──────────────────────────── Laravel app (one codebase) ─────────────────────────────┐
  │  host → agency (ResolveTenant)                                                       │
  │                                                                                      │
  │  admin.<platform>/platform   Super-admin panel   (guard: platform, DB role: platform)│
  │  <agency>.<platform>/crm     Agency CRM          (guard: web, staff roles, MFA)      │
  │  <agency>.<platform>/portal  Freelancer portal   (guard: web, freelancer role)       │
  │  <agency>.<platform>/api/v1  Integration API     (HMAC keys per agency)             │
  │                                                                                      │
  │  Queue workers (emails, imports, exports) re-enter the agency from the job payload   │
  └───────────────┬───────────────────────────────┬──────────────────────────────────────┘
                  │                               │
        PostgreSQL 16 (RLS per agency)    Redis (cache, sessions, queues)    R2/S3 private files
```

## Code layout

```
app/
  Actions/                 Use-case classes (one public handle()); controllers and Filament pages call these
    Platform/ProvisionTenant.php
    UpsertFreelancerFromIntake.php
  Audit/AuditLogger.php    Append-only audit trail
  Console/Commands/        platform:create-admin, platform:create-tenant, platform:check-db
  CountryPacks/            Contract + Cameroon pack + registry (ADR 0003)
  Enums/Role.php           Built-in roles
  Filament/
    Platform/              Super-admin resources (agencies, plans...)
    Crm/                   Agency staff resources (freelancers, clients, requests...)
    Portal/                Freelancer pages
  Http/
    Controllers/Api/V1/    JSON API
    Middleware/            ResolveTenant, UsePlatformConnection, VerifyIntegrationSignature, SetLocale, SecurityHeaders
    Requests/              Validation
  Models/                  Eloquent models; tenant-owned ones use BelongsToTenant
  Policies/                Role permission checks per model
  Support/Database/        RowLevelSecurity helper for migrations
  Tenancy/                 TenantContext, TenantScope, BelongsToTenant, QueueTenancy
config/platform.php        Base domain, locales, country packs, quiz grading
database/migrations/       Each tenant table: tenant_id + RLS + grants in the same file
docs/                      This file, ADRs, roadmap, security
lang/en.json, lang/fr.json UI strings (Filament ships its own FR/EN)
tests/                     Feature tests run against real Postgres with the production role layout
```

## Data model

See [schema.md](schema.md).

## Conventions

- Business logic lives in `app/Actions`, not in Filament pages or controllers, so the API and panels share it.
- Every user-facing string goes through `__()` with keys in both `lang/en.json` and `lang/fr.json` (a test checks they match).
- Times stored in UTC; shown in the agency's time zone (default Africa/Douala).
- Money as decimal(12,2) with a currency column; XAF by default.
- Personal data never goes into logs or audit `meta`.
