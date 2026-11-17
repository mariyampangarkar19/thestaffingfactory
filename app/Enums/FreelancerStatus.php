<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a freelancer is in the agency's review.
 */
enum FreelancerStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Screening = 'screening';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Screening => __('Screening'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Inactive => __('Inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Screening => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Inactive => 'gray',
        };
    }
}
