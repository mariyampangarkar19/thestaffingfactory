<?php

namespace App\Filament\Crm\Resources\Clients\Pages;

use App\Filament\Crm\Resources\Clients\ClientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::recordHeaderActions(),
            ...(static::getResource()::hasPage('edit') ? [EditAction::make()] : []),
        ];
    }
}
