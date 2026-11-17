<?php

namespace App\Filament\Crm\Resources\Announcements\Pages;

use App\Filament\Crm\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
}
