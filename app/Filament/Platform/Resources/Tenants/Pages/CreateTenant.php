<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Actions\Platform\ProvisionTenant;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ProvisionTenant::class)->handle($data);
    }
}
