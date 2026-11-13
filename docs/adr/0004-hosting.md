# ADR 0004: Hosting on one managed VPS to start

Status: proposed (13 Nov 2026), needs the client's go-ahead because it creates monthly costs in their name.

**Recommended start (about USD 20–25 per month, prices to be re-checked when ordering):**

| Item | Choice | Approx. monthly |
|---|---|---|
| Server | Hetzner Cloud, 2 vCPU / 4 GB (Germany/Finland region; good latency to Central Africa via Europe) | USD 5–7 |
| Server backups | Hetzner automatic backups (7 daily images) | USD 1–2 |
| Server management | Laravel Forge "Hobby" (provisioning, SSL, deploys, OS security updates, firewall) | USD 12 |
| Files + DB dumps | Cloudflare R2 private bucket (free tier 10 GB, no download fees) | USD 0–1 |
| Transactional email | Brevo or Amazon SES (client mailboxes stay on Microsoft 365) | USD 0–5 |
| Domain + DNS | Cloudflare DNS, wildcard `*.platform-domain` | domain only |

Postgres and Redis run on the same server at first. Nightly encrypted `pg_dump` to R2 plus server images gives two independent backups; a restore drill is part of milestone 5.

**Scale path, no code change:** move Postgres to a managed database, add a second app server behind a load balancer, move Redis out. Tenants on separate databases are possible later for a large customer.

**Ownership:** all accounts are opened in the client's name with their card; we get team access. This follows the contract review lesson (hosting must be owned by the client).
