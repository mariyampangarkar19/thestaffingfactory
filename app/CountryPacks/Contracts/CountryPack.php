<?php

namespace App\CountryPacks\Contracts;

/**
 * A country pack adds what differs by country: extra freelancer fields, required documents,
 * ID validation, currency and (later) payroll or visa rules such as UAE WPS.
 *
 * Packs are plain classes registered in config/platform.php and switched on per agency
 * (tenants.settings.country_packs). Core code never checks country codes directly; it asks the pack.
 * Paid add-on packs can live in their own Composer package and register the same way.
 */
interface CountryPack
{
    /** ISO 3166-1 alpha-2, e.g. "CM". */
    public function code(): string;

    public function currency(): string;

    /**
     * Extra fields stored in freelancer_profiles.country_data.
     *
     * @return array<string, array{label: array{en: string, fr: string}, rules: list<string>}>
     */
    public function freelancerFields(): array;

    /**
     * Document types a freelancer must upload before approval.
     *
     * @return array<string, array{en: string, fr: string}>
     */
    public function requiredDocuments(): array;

    /** E.164 prefix used to normalise local phone numbers, e.g. "+237". */
    public function phonePrefix(): string;
}
