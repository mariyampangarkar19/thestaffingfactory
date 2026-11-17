<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Staffing request status. Allowed moves are listed in next().
 */
enum RequestStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::InProgress => __('In progress'),
            self::Confirmed => __('Confirmed'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Statuses this one may move to. Completed and Cancelled are final.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::InProgress, self::Confirmed, self::Cancelled],
            self::InProgress => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
