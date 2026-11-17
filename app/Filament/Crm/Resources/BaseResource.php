<?php

namespace App\Filament\Crm\Resources;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;

/**
 * Shared hooks for CRM resources. A resource overrides these to add its own buttons;
 * the generic page classes call them, so pages never need editing when a module grows.
 */
abstract class BaseResource extends Resource
{
    /** Labels are written as they should appear ("Shifts and assignments"), so they are not re-capitalised. */
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    /**
     * Buttons shown on a record's page (status changes and similar).
     *
     * @return list<Action|ActionGroup>
     */
    public static function recordHeaderActions(): array
    {
        return [];
    }

    /**
     * Buttons shown above the list when the module has no plain "create" form.
     *
     * @return list<Action>
     */
    public static function listHeaderActions(): array
    {
        return [];
    }
}
