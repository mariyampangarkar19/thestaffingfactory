<?php

namespace App\Filament\Crm\Resources\Documents\Pages;

use App\Filament\Crm\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::listHeaderActions();
    }
}
