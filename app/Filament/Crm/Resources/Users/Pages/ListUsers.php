<?php

namespace App\Filament\Crm\Resources\Users\Pages;

use App\Filament\Crm\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::listHeaderActions();
    }
}
