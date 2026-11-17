<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How a staffing request reached the agency.
 */
enum RequestChannel: string implements HasColor, HasLabel
{
    case Website = 'website';
    case Manual = 'manual';
    case Phone = 'phone';
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => __('Website form'),
            self::Manual => __('Entered by operator'),
            self::Phone => __('Phone'),
            self::Email => __('Email'),
            self::WhatsApp => __('WhatsApp'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Website => 'info',
            self::Manual => 'gray',
            self::Phone => 'gray',
            self::Email => 'gray',
            self::WhatsApp => 'gray',
        };
    }
}
