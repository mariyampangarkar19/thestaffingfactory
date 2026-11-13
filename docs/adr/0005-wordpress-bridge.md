# ADR 0005: The WordPress site stays; it sends sign-ups to the platform over a signed API

Status: accepted (13 Nov 2026)

The public site (WordPress + Elementor) and the Phase 1 "Applicant Quiz" plugin stay live. The plugin will POST each freelancer sign-up and quiz result to `POST https://<agency host>/api/v1/intake/freelancers`.

**Authentication: HMAC, not a bearer token.** Headers `X-Key-Id`, `X-Timestamp`, `X-Signature = hex(HMAC-SHA256(secret, "{timestamp}.{body}"))`. Requests older than 5 minutes and replays are rejected; keys are per agency, scoped (`intake:freelancers`), revocable, and stored encrypted. The key only works on its own agency's host.

**Idempotent.** The plugin's entry id is `external_ref`, so retries update instead of duplicating. The Google Sheet sync keeps working in parallel until the agency switches it off.

**Grading** uses the same rule as the plugin (PASS at 85+, A 95+, B 90+, C 85+) in `QuizResult::grade()`, covered by a unit test.

Milestone 2 adds: plugin-side sender with retry queue, CV transfer, hirer form intake (`intake:clients`), and a "Freelancer portal" link/button on the site.
