<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Shift status.
 */
enum ShiftStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => __('Planned'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }
}
