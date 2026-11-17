<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Document review status.
 */
enum DocumentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('To review'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Expired => __('Expired'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
        };
    }
}
