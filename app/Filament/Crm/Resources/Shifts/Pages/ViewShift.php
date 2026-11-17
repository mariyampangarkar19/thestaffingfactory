<?php

namespace App\Filament\Crm\Resources\Shifts\Pages;

use App\Filament\Crm\Resources\Shifts\ShiftResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShift extends ViewRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::recordHeaderActions(),
            ...(static::getResource()::hasPage('edit') ? [EditAction::make()] : []),
        ];
    }
}
