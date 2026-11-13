# ADR 0001: Laravel 13 + PostgreSQL 16 + Filament 5

Status: accepted (13 Nov 2026)

## Context

- Budget: USD 3,000 (AED 11,000) for five milestones, one developer, and the client wants the source code and to be able to hire someone else later.
- Product: agency CRM, mobile-first freelancer portal, super-admin for a multi-agency SaaS, French and English.
- The client already runs WordPress (PHP). The October proposal named Laravel + PostgreSQL.
- Users are mostly on phones on mobile data in Cameroon, so pages must be light.

## Options considered

| Option | For | Against |
|---|---|---|
| **Laravel + Filament (chosen)** | Mature auth, queues, mail, validation, encryption built in. Filament gives tables, forms, filters, exports and MFA for admin screens with little code. PHP developers are easy to find and affordable in Cameroon/West Africa. Runs on a USD 5–10 server. | Filament screens are less custom than a hand-made SPA. |
| Next.js + NestJS (TypeScript) | One language front and back, great for custom UX. | Two apps to build, host and secure; auth, admin tables and RBAC all hand-built; budget would go into plumbing. |
| Extend WordPress | Client knows it. | Weak fit for multi-tenant SaaS, row-level isolation, and long-term maintainability. Rejected in October. |
| Django | Good admin. | Smaller talent pool locally; no advantage over Laravel here. |

## Decision

- **PHP 8.4, Laravel 13** for the application.
- **Filament 5** for all three panels: Platform (super-admin), CRM (agency staff), Portal (freelancers). Custom Blade/Livewire pages where a screen needs a bespoke layout (e.g. freelancer onboarding wizard, shift offer cards).
- **PostgreSQL 16** for data, chosen over MySQL for row-level security (ADR 0002), JSONB, and strong constraints.
- **Redis** for cache, sessions and queues in production (database driver is fine locally).
- **PHPUnit** against real Postgres, **Pint** for style, **Larastan** for static analysis, **GitHub Actions** CI.

## Consequences

- One deployable app, one language, low hosting cost.
- A JSON API (`/api/v1`) is already in place for the WordPress bridge; a mobile app later can reuse it.
