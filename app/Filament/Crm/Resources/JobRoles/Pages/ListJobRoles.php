<?php

namespace App\Filament\Crm\Resources\JobRoles\Pages;

use App\Filament\Crm\Resources\JobRoles\JobRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobRoles extends ListRecords
{
    protected static string $resource = JobRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
