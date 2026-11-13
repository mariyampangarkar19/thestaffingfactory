# Database schema

44 tables. Every agency-owned table has `tenant_id` with forced row-level security (see ADR 0002). Status columns are restricted by database CHECK constraints. IDs are UUIDv7. Times are UTC.

## Platform (not agency-owned)

| Table | Purpose |
|---|---|
| tenants, tenant_domains | Agencies and the host names that lead to them. App can read only. |
| platform_admins | Super-admins. The web app's database role has no access. |
| plans | Subscription plans: price, billing period, limits. |
| platform_settings | General platform settings. |
| permissions, roles, role_has_permissions | Built-in roles and what they may do. |

## Agency-owned

| Area | Tables | Notes |
|---|---|---|
| Accounts | users, model_has_roles, model_has_permissions, password_reset_tokens, notifications | Staff and freelancers of one agency. |
| Subscription | subscriptions, subscription_payments | Agency can read its own; only the platform side writes. |
| Freelancers | freelancer_profiles, job_roles, freelancer_job_role, quiz_results, freelancer_unavailabilities, freelancer_evaluations | Status: new, screening, approved, rejected, inactive. |
| Customers | clients, client_contacts | CRM records only; customers never log in. |
| Operations | staff_requests, staff_request_positions, shifts, assignments, timesheets | Request status: new, in_progress, confirmed, completed, cancelled. |
| Payments | pay_rates, payments, payment_items | Records of what freelancers earned and were paid. A timesheet can be paid once. |
| Shared records | documents, notes, announcements | Attach to freelancers, customers, requests, assignments. |
| Integration | integration_keys, intake_submissions | Website forms; every submission gets a receipt. |
| Audit | audit_logs | Append-only. |

## Main chain

```
client ─< staff_request ─< staff_request_position ─< shift ─< assignment >─ freelancer_profile
                                                                │
                                                            timesheet ─< payment_item >─ payment
```

- A **request** has one or more **positions** ("4 waiters"). Each position gets one or more **shifts** (date, hours, location).
- An **assignment** puts one freelancer on one shift and copies the pay rate at that moment.
- The freelancer submits a **timesheet**; the agency approves or adjusts it. Approved minutes x rate = earnings.
- A **payment** groups approved timesheets and is marked paid with method and reference.
