<?php

namespace App\Filament\Crm\Resources\StaffRequests\Pages;

use App\Actions\Requests\CreateStaffRequest as CreateStaffRequestAction;
use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffRequest extends CreateRecord
{
    protected static string $resource = StaffRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $positions = array_values($data['positions'] ?? []);
        unset($data['positions']);

        return app(CreateStaffRequestAction::class)->handle($data, $positions, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
