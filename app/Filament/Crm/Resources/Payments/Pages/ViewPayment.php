<?php

namespace App\Filament\Crm\Resources\Payments\Pages;

use App\Filament\Crm\Resources\Payments\PaymentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...static::getResource()::recordHeaderActions(),
            ...(static::getResource()::hasPage('edit') ? [EditAction::make()] : []),
        ];
    }
}
