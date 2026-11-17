<?php

namespace App\Filament\Crm\Resources\Announcements\Pages;

use App\Filament\Crm\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
