<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Timesheet review status.
 */
enum TimesheetStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Adjusted = 'adjusted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => __('Awaiting approval'),
            self::Approved => __('Approved'),
            self::Adjusted => __('Approved with changes'),
            self::Rejected => __('Rejected'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Adjusted => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::Approved, self::Adjusted], true);
    }
}
