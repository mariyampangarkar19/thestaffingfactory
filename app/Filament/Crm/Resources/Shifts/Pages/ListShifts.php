<?php

namespace App\Filament\Crm\Resources\Shifts\Pages;

use App\Filament\Crm\Resources\Shifts\ShiftResource;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::listHeaderActions();
    }
}
