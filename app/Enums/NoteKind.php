<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Kind of note or communication record.
 */
enum NoteKind: string implements HasColor, HasLabel
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Meeting = 'meeting';

    public function getLabel(): string
    {
        return match ($this) {
            self::Note => __('Internal note'),
            self::Call => __('Phone call'),
            self::Email => __('Email'),
            self::WhatsApp => __('WhatsApp'),
            self::Meeting => __('Meeting'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Note => 'gray',
            self::Call => 'info',
            self::Email => 'info',
            self::WhatsApp => 'info',
            self::Meeting => 'info',
        };
    }
}
