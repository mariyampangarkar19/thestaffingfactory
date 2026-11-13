# Security and data protection

What is in place now, and what each milestone adds. Not legal advice; the client's lawyer confirms the legal items.

## In place (milestone 1 scaffold)

| Area | Control |
|---|---|
| Tenant isolation | App global scope + Postgres forced RLS + separate DB roles (ADR 0002); tested with raw SQL |
| Authentication | Separate guards for super-admins and agency users; super-admin table unreadable by the app role |
| MFA | Authenticator app (TOTP) with recovery codes, required for super-admins and agency staff; optional for freelancers |
| Authorisation | Roles + permissions per agency (spatie/laravel-permission teams); Laravel policies on every resource |
| Passwords | Hashed (bcrypt); production policy min 12, mixed case, numbers, checked against known breaches (HIBP k-anonymity) |
| Sessions | Encrypted, HTTP-only, host-only cookies, CSRF on every form |
| API | HMAC-signed requests, 5-minute window, replay rejection, per-key scopes, revocation, rate limit |
| Secrets at rest | MFA secrets, recovery codes and integration secrets encrypted with `APP_KEY` |
| IDs | UUIDv7 everywhere (not guessable/enumerable) |
| Audit | Append-only `audit_logs` (DB role cannot update/delete); stores field names, not personal values |
| Headers | HSTS (on HTTPS), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP, base CSP |
| Supply chain | `composer audit` in CI, Dependabot weekly, lock files committed |
| Bulk deletes | Not offered in the CRM; agencies cannot be deleted with one click |

## Added later

- M2: private file storage with signed short-lived URLs, MIME/extension/size checks, virus-scan hook, per-agency storage prefix
- M3: personal-data export and anonymisation, data retention rules, audit log viewer, login/2FA events in the audit log
- M4: impersonation only with audit trail; custom-domain verification
- M5: full CSP with nonces, ASVS L2 review, restore drill, incident runbook

## Data protection notes

- Cameroon Law 2024/017 (in force June 2026): consent captured at sign-up (`consented_at`), purpose limitation, data-subject rights tools (M3), declaration with the Data Protection Authority by the agency (client's lawyer).
- Hirers abroad will only see what an agency shares; there is no hirer portal in this scope.
- Each leasing agency is the data controller for its people; the platform operator is a processor. A data-processing agreement template is part of M4.

## Reporting a vulnerability

See `SECURITY.md`.
