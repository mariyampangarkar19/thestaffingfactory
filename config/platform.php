<?php

use App\CountryPacks\Cameroon\CameroonPack;

return [

    /*
     * Working name only. The client has not chosen the product name yet.
     */
    'name' => env('PLATFORM_NAME', 'Staffing Platform'),

    /*
     * Agencies get {slug}.{base_domain} by default and can add their own domain later.
     * The super-admin panel lives on {admin_subdomain}.{base_domain} and nowhere else.
     */
    'base_domain' => env('PLATFORM_BASE_DOMAIN', 'staffing.test'),
    'admin_subdomain' => env('PLATFORM_ADMIN_SUBDOMAIN', 'admin'),

    /*
     * Postgres roles the migrations grant rights to. Must match the
     * usernames of the pgsql and pgsql_platform connections.
     */
    'db_roles' => [
        'app' => env('DB_USERNAME', 'sp_app'),
        'platform' => env('DB_PLATFORM_USERNAME', 'sp_platform'),
    ],

    'locales' => ['en', 'fr'],

    /*
     * Country packs add country-specific fields, documents and rules.
     * Each agency enables the packs it pays for in tenants.settings.country_packs.
     */
    'country_packs' => [
        'CM' => CameroonPack::class,
    ],

    'intake' => [
        // Signed requests older than this are rejected (replay protection).
        'max_clock_skew_seconds' => 300,
    ],

    /*
     * Phase 1 quiz grading, kept identical to the WordPress plugin.
     */
    'quiz' => [
        'pass_mark' => 85,
        'grades' => ['A' => 95, 'B' => 90, 'C' => 85],
    ],
];
