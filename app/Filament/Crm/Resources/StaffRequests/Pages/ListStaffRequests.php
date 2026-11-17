<?php

namespace App\Filament\Crm\Resources\StaffRequests\Pages;

use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffRequests extends ListRecords
{
    protected static string $resource = StaffRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
