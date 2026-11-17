# Phase 2 roadmap

Agreed with the client on 8 Nov 2026: **AED 11,000 (USD 3,000)**, five milestones of the scope below. Work on each milestone starts when its payment is received. Anything not listed here goes through a change request.

**In scope:** the agency CRM and freelancer portal from the competing quote (Trater, Oct 2026), **without a hirer portal**, plus packaging as a multi-agency SaaS that can be leased worldwide. French and English throughout.

**Out of scope (change request if wanted):** hirer/client logins and portal, company verification (KYB), quotes and invoices sent to hirers, e-signature of contracts, online card/mobile-money payments, native mobile apps, country packs other than Cameroon (paid add-ons, e.g. UAE WPS/visa).

## Status on 17 Nov 2026: first working build

Delivery follows the sections of the Trater quote. Sections 1 and 2 (the "main platform modules") are built and run locally with demo data; see the README, "Try it with demo data".

**Section 1, Agency Portal (CRM): built**

| Quote item | Where | Notes |
|---|---|---|
| Dashboard | CRM home | Freelancers, customers, new requests, active / upcoming / completed assignments, registrations and timesheets waiting |
| Customer management | People > Customers | Company, contacts, requests, notes, documents. Customers have no login |
| Staffing requests | Operations > Staffing requests | Website or manual, positions and numbers, operator in charge, New / In progress / Confirmed / Completed / Cancelled |
| Customer communication | "Notes and contact history" tab | Calls, emails, WhatsApp, meetings, internal notes |
| Freelancer management | People > Freelancers | Review and approve, quiz result, positions, availability, documents, evaluations, history |
| Assignments and scheduling | Operations > Shifts, Calendar | Assign with checks for approval, clashes, unavailable dates and headcount |
| Timesheets and payments | Finance | Approve, adjust or reject hours; payments built from approved hours; mark paid |
| Contracts and documents | Records > Documents | Private storage, review, expiry dates, share with freelancer |
| Reports and exports | Records > Reports | Assignments, hours, payments, freelancers; Excel, or print view saved as PDF |
| Team and operators | Settings > Team | Invite by email, six roles, remove access |

**Section 2, Freelancer Portal: built**

Home (next assignments, hours to submit, last payment, announcements), My assignments, My calendar, Timesheets (submit hours), Payments, My documents (CV, photo, intro video, certificates), My profile, in-portal notifications. Freelancers cannot browse or apply for work.

**Known gaps in this build**

- Not deployed yet: no test link until hosting accounts exist (section 7).
- Notifications are in the portal only. Email, SMS and WhatsApp alerts need a provider (open decision 5).
- PDF export is the browser's "Save as PDF" on a print view, not a generated file.
- No virus scan on uploads yet (type, size and private storage are enforced).
- The freelancer portal uses the same component library as the CRM with a phone layout; a fully custom portal design is a later decision.
- Website requests reach the CRM through the same signed API as registrations; the request endpoint itself comes with section 4.
- Larastan is not yet blocking in CI.

**Next:** section 3 (super admin: agencies, plans, manual payment records), section 4 (website forms), section 5 (multi-agency packaging), section 6 (security audit), section 7 (test link, QA, deployment).

The milestone lists below are the original plan of 8 Nov and are kept for reference; where they differ from the quote's sections, the quote wins.

## Milestone 1: Setup (AED 2,500)

Goal: a secure, deployed foundation the client owns.

- [x] Repository, Laravel 13 + Postgres 16 + Filament 5 scaffold (this commit)
- [x] Per-agency isolation in app and database (ADR 0002) with tests
- [x] Three panels: Platform (super-admin), CRM (agency staff), Portal (freelancers); MFA required for staff and super-admins
- [x] Built-in roles and permission matrix (owner, manager, recruiter, sales, finance, viewer, freelancer)
- [x] Signed intake API for the WordPress plugin (ADR 0005)
- [x] French/English switching (user choice > `?lang=` > browser > agency default)
- [x] CI: tests on real Postgres, code style, dependency audit, Dependabot
- [ ] Client opens hosting accounts in their name (ADR 0004); we deploy staging + production with HTTPS and wildcard sub-domains
- [ ] Backups: nightly encrypted DB dump to R2 + server images; first restore test
- [ ] Error monitoring (Sentry free tier or Laravel Nightwatch) and uptime check
- [ ] Transfer the repository to the client's GitHub account (or add them as owner)
- [ ] Larastan clean at level 6, then made blocking in CI

**Done when:** TSF agency exists on production, staff can log in with 2FA, a test sign-up from the website appears in the CRM.

## Milestone 2: Freelancer portal + website link (AED 2,500)

- Freelancer account activation from website sign-up (invite link by email/SMS/WhatsApp), self-registration on the portal
- Step-by-step onboarding: personal details, country-pack fields, skills/roles, languages, photo
- Documents and certificates upload (private storage, type/size checks, virus scan hook, expiry dates)
- Availability calendar set by the freelancer
- Quiz results and grade shown; retake rules
- Shift offers: accept/decline, upcoming shifts, history
- Pay history (read-only, from milestone 3 data)
- WordPress plugin update: send sign-ups and quiz results to the API with retry; "Freelancer login" link on the site; hirer form to `intake:clients`
- Mobile-first check on low-end Android over 3G

## Milestone 3: Agency CRM (AED 2,500)

- Freelancer pipeline: screening, document review/approval, approve/reject with reasons, notes, tags
- Clients (hirer companies) and contacts, staff requests (role, dates, location, headcount)
- Matching shortlist (role, city, availability, grade, languages) and assignment/offer sending
- Calendar of shifts, timesheets (hours in/out, approval)
- Freelancer payments due/paid, rates per role, XAF and other currencies
- Reports and exports (CSV/Excel): sign-ups by source (utm), pass rate, fill rate, hours, payments
- Agency users and roles management (owner), audit log viewer
- Data-protection tools: export a person's data, anonymise on request (Cameroon Law 2024/017)

## Milestone 4: Multi-agency SaaS package (AED 2,500)

- Agency sign-up (or super-admin creation) with trial, plans and limits (users, freelancers, storage)
- Per-agency branding: logo, colours, sender name, custom domain with verification and automatic HTTPS
- Per-agency settings: default language, time zone, currency, enabled country packs
- Subscription records and invoices for agencies (manual payment recording; online gateway is an open decision below)
- Super-admin: agencies list, suspend/reactivate, usage, impersonation with audit trail and agency consent
- Country-pack interface finalised so paid packs (e.g. UAE) plug in without core changes
- Legal templates per agency: privacy notice, terms, data-processing agreement

## Milestone 5: Testing, launch, handover, 30 days of fixes (AED 1,000)

- End-to-end tests of the main journeys, load test (e.g. 200 concurrent users), security review (OWASP ASVS L2 checklist), dependency audit
- Full Content-Security-Policy with nonces
- Backup restore drill, runbook (deploy, rollback, rotate keys, restore)
- Launch for TSF; import existing Google Sheet data
- Source code, all credentials and accounts handed to the client; admin training (FR/EN)
- 30 days of bug fixes after launch

## Open decisions (client)

1. **Product name**: OPOPO (One People One Power), set in `config/branding.php`. Still to do on the client side: logo files, and registering the name with OAPI.
2. **Platform domain** for agency sub-domains (e.g. `app.<name>.com`), and TSF's own address (e.g. `portal.thestafffactory.com`).
3. **Hosting accounts** in the client's name (ADR 0004).
4. **Online payments for agency subscriptions**: Stripe does not onboard Cameroonian businesses; options are Paddle (merchant of record), Flutterwave/CinetPay (cards + mobile money), or manual invoicing at first.
5. **SMS/WhatsApp notifications** provider and budget (email is included).
