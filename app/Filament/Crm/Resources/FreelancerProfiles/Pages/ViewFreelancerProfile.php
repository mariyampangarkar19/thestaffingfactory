<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Pages;

use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFreelancerProfile extends ViewRecord
{
    protected static string $resource = FreelancerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::recordHeaderActions(),
            ...(static::getResource()::hasPage('edit') ? [EditAction::make()] : []),
        ];
    }
}
