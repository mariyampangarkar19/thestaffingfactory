# Staffing Platform (working name)

Multi-agency staffing CRM and freelancer portal. First agency: **The Staffing Factory** (Cameroon). Built to be leased to other recruitment agencies as SaaS.

- **Stack:** PHP 8.4 · Laravel 13 · Filament 5 · PostgreSQL 16 · Redis
- **Panels:** `/platform` super-admin · `/crm` agency staff · `/portal` freelancers · `/api/v1` integrations
- **Languages:** French and English

Read first: [architecture](docs/architecture.md) · [roadmap](docs/roadmap.md) · [security](docs/security.md) · [decisions](docs/adr)

## Try it with demo data (Docker only)

Requirement: Docker Desktop. Nothing else to install.

```bash
docker compose --profile app up -d --build     # first run takes a few minutes
```

Open **http://tsf.localhost:8000/crm** (agency) or **http://tsf.localhost:8000/portal** (freelancer). Emails the app sends
(invitations, password links) appear at http://localhost:8025.

| Sign in as | Email | Sees |
|---|---|---|
| Owner | `owner@demo.test` | Everything, including team and settings |
| Manager | `manager@demo.test` | Operations, timesheet approval, reports |
| Recruiter | `recruiter@demo.test` | Freelancers, shifts and assignments |
| Sales | `sales@demo.test` | Customers and staffing requests |
| Finance | `finance@demo.test` | Timesheets, payments, reports |
| Read only | `viewer@demo.test` | Can look, cannot change |
| Freelancer (portal) | `freelancer@demo.test` | Their own assignments, hours, payments |

Password for all of them: the value of `DEMO_PASSWORD` in `.env.example`. Start again from clean data with
`docker compose --profile app down -v` and the `up` command above. The demo seeder refuses to run in production.

## Run it locally (for development)

Requirements: PHP 8.3+ with `pdo_pgsql` and `intl`, Composer, Node 20+, Docker (for Postgres/Redis/Mailpit) or a local Postgres 16.
In `php.ini` set `upload_max_filesize = 32M` and `post_max_size = 40M` (intro videos are up to 30 MB).

```bash
docker compose up -d                 # Postgres with the 3 roles from docker/postgres/init.sql, Redis, Mailpit (http://localhost:8025)
cp .env.example .env
composer install && npm ci && npm run build
php artisan key:generate
composer migrate-owner               # migrate as the owner role, seed roles, run the isolation check
php artisan db:seed --class=DemoSeeder    # optional: demo agency "tsf" with sample data

# Hosts: agencies are found by host name. Add to /etc/hosts:
#   127.0.0.1 admin.staffing.test tsf.staffing.test
php artisan platform:create-admin you@example.com
php artisan queue:work &             # sends set-password emails (see Mailpit)
php artisan serve --host=0.0.0.0
```

Then open `http://tsf.staffing.test:8000/crm` (agency), `http://tsf.staffing.test:8000/portal` (freelancer) or
`http://admin.staffing.test:8000/platform` (super-admin). To create a real agency instead of the demo one:
`php artisan platform:create-tenant tsf "The Staffing Factory" owner@example.com --owner-name="Owner"`.

## Tests

```bash
createdb staffing_test   # or use the one from docker/postgres/init.sql
php artisan test         # runs against real Postgres; row-level security is exercised
vendor/bin/pint          # code style
```

## Database roles

The app never connects as the table owner. See [ADR 0002](docs/adr/0002-tenant-isolation.md).

| .env | Role | Purpose |
|---|---|---|
| `DB_USERNAME` | `sp_app` | Web app and workers; row-level security always applies |
| `DB_OWNER_USERNAME` | `sp_owner` | Migrations only |
| `DB_PLATFORM_USERNAME` | `sp_platform` | Super-admin panel (BYPASSRLS) |

## Deploy (summary)

`composer install --no-dev -o` → `php artisan migrate --database=pgsql_owner --force` → `php artisan db:seed --force` → `php artisan platform:check-db` → `php artisan optimize` → restart queue workers. Full runbook in milestone 5.

## Licence

Proprietary. All rights reserved by the client on full payment (see the Phase 2 agreement).
