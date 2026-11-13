# ADR 0003: Country packs as pluggable modules

Status: accepted (13 Nov 2026)

Country-specific rules (UAE WPS payroll files and visa tracking, Cameroon CNI, Nigerian NIN, etc.) are paid add-ons. Core code must not grow `if ($country === 'AE')` branches.

**Decision.** A country pack implements `App\CountryPacks\Contracts\CountryPack` (extra freelancer fields, required documents, currency, phone prefix; later: payroll exports, compliance checks, contract templates). Packs are registered in `config/platform.php` and enabled per agency in `tenants.settings.country_packs`. Pack fields are stored in `freelancer_profiles.country_data` (JSONB) and validated with the pack's rules, so adding a country needs no migration.

Cameroon (`CM`) ships with every agency. A paid pack can live in its own private Composer package, so it can be licensed separately.

The interface will grow during milestones 2–4 as real needs appear (e.g. `payrollExporters()`, `contractTemplates()`); additions must be backwards compatible or bump all packs together.
