<?php

namespace App\Filament\Crm\Resources\StaffRequests\Pages;

use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStaffRequest extends ViewRecord
{
    protected static string $resource = StaffRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::recordHeaderActions(),
            ...(static::getResource()::hasPage('edit') ? [EditAction::make()] : []),
        ];
    }
}
