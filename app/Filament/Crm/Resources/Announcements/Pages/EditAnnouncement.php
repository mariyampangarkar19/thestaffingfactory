<?php

namespace App\Filament\Crm\Resources\Announcements\Pages;

use App\Filament\Crm\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;
}
