<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Pages;

use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFreelancerProfile extends EditRecord
{
    protected static string $resource = FreelancerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
