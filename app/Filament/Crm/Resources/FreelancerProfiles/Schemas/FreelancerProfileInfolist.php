<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FreelancerProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('first_name')->label(__('First name')),
            TextEntry::make('last_name')->label(__('Last name')),
            TextEntry::make('email')->label(__('Email'))->placeholder('—'),
            TextEntry::make('phone')->label(__('Phone'))->placeholder('—'),
            TextEntry::make('city')->label(__('City'))->placeholder('—'),
            TextEntry::make('status')->label(__('Status'))->badge(),
            TextEntry::make('source')->label(__('Source'))->placeholder('—'),
            TextEntry::make('consented_at')->label(__('Consent given'))->dateTime()->placeholder('—'),
        ]);
    }
}
