<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Pages;

use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFreelancerProfiles extends ListRecords
{
    protected static string $resource = FreelancerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
