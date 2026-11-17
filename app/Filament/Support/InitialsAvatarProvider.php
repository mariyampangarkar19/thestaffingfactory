<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Initials on the brand teal, drawn locally. The default provider sends every user's name to a third-party website.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = Str::of(Filament::getNameForDefaultAvatar($record))
            ->trim()->explode(' ')->filter()->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" fill="#006876"/>'
            .'<text x="32" y="33" dy=".35em" text-anchor="middle" font-family="Roboto,Arial,sans-serif" font-size="26" font-weight="500" fill="#fff">'
            .e($initials).'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
