<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Customer lifecycle.
 */
enum ClientStatus: string implements HasColor, HasLabel
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lead => __('Lead'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Lead => 'info',
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }
}
