<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FreelancerProfileForm
{
    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            'new' => __('New'),
            'screening' => __('Screening'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'inactive' => __('Inactive'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        // tenant_id is never a form field: it is set from the current agency (BelongsToTenant).
        return $schema->components([
            Section::make(__('Identity'))->columns(2)->schema([
                TextInput::make('first_name')->label(__('First name'))->required()->maxLength(100),
                TextInput::make('last_name')->label(__('Last name'))->maxLength(100),
                DatePicker::make('date_of_birth')->label(__('Date of birth'))->maxDate(now()->subYears(16)),
                Select::make('status')->label(__('Status'))->options(self::statuses())->required()->default('new'),
            ]),
            Section::make(__('Contact'))->columns(2)->schema([
                TextInput::make('email')->label(__('Email'))->email()->maxLength(190),
                TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(32),
                TextInput::make('whatsapp')->label(__('WhatsApp'))->tel()->maxLength(32),
                TextInput::make('city')->label(__('City'))->maxLength(100),
            ]),
            Section::make(__('Skills'))->columns(2)->schema([
                Select::make('jobRoles')->label(__('Positions'))->multiple()->preload()
                    ->relationship('jobRoles', app()->getLocale() === 'fr' ? 'name_fr' : 'name_en'),
                Select::make('languages')->label(__('Languages'))->multiple()->options(['fr' => 'Français', 'en' => 'English']),
            ]),
        ]);
    }
}
