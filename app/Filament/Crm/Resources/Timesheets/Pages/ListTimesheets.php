<?php

namespace App\Filament\Crm\Resources\Timesheets\Pages;

use App\Filament\Crm\Resources\Timesheets\TimesheetResource;
use Filament\Resources\Pages\ListRecords;

class ListTimesheets extends ListRecords
{
    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::listHeaderActions();
    }
}
