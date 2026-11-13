# Contributing

Read `docs/architecture.md` and `docs/adr/0002-tenant-isolation.md` before changing anything.

Non-negotiables:
- Every tenant-owned table: `tenant_id` FK, `RowLevelSecurity::protect()` and grants in the same migration. Every tenant model: `use BelongsToTenant`.
- Never call `withoutGlobalScopes()` on tenant models outside the Platform panel. Never connect the web app as the owner role.
- Business logic in `app/Actions`; Filament pages and controllers stay thin. Policies for every resource.
- All UI text through `__()`, with keys in both `lang/en.json` and `lang/fr.json`.
- No personal data in logs or audit meta.
- Run `php artisan test` (needs Postgres with the roles in `docker/postgres/init.sql`) and `vendor/bin/pint --test` before committing.
