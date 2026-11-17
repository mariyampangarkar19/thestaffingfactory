<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * General availability set by the agency.
 */
enum AvailabilityStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Limited = 'limited';
    case Unavailable = 'unavailable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Limited => __('Limited availability'),
            self::Unavailable => __('Unavailable'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Limited => 'warning',
            self::Unavailable => 'gray',
        };
    }
}
