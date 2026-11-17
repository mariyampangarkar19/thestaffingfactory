<?php

namespace App\Support;

/**
 * Read access to config/branding.php, so no other code needs to know the product's name or where its logo lives.
 */
final class Branding
{
    public static function name(): string
    {
        return (string) config('branding.name');
    }

    public static function tagline(): ?string
    {
        return filled(config('branding.tagline')) ? (string) config('branding.tagline') : null;
    }

    public static function logoUrl(): ?string
    {
        return self::asset(config('branding.logo'));
    }

    public static function logoHeight(): string
    {
        return (string) (config('branding.logo_height') ?: '2rem');
    }

    public static function faviconUrl(): ?string
    {
        return self::asset(config('branding.favicon'));
    }

    public static function showPoweredBy(): bool
    {
        return (bool) config('branding.show_powered_by');
    }

    private static function asset(mixed $path): ?string
    {
        return filled($path) ? asset(ltrim((string) $path, '/')) : null;
    }
}
