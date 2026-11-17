<?php

namespace App\Filament\Crm\Resources\Payments\Pages;

use App\Filament\Crm\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return static::getResource()::listHeaderActions();
    }
}
