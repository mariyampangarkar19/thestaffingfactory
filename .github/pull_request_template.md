## What changed

## Why

## Checklist
- [ ] New tenant-owned tables have `tenant_id`, call `RowLevelSecurity::protect()` and grants in the migration
- [ ] New models with `tenant_id` use `BelongsToTenant`
- [ ] New screens/actions have a policy check (role permission), not only a hidden button
- [ ] User-facing text is wrapped in `__()` and added to `lang/en.json` and `lang/fr.json`
- [ ] No personal data in logs, audit `meta`, or error messages
- [ ] Tests added; `php artisan test` and `vendor/bin/pint --test` pass
