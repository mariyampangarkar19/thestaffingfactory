<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A freelancer's place on a shift.
 */
enum AssignmentStatus: string implements HasColor, HasLabel
{
    case Assigned = 'assigned';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Assigned => __('Assigned'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
            self::NoShow => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Assigned, self::Confirmed, self::Completed], true);
    }
}
