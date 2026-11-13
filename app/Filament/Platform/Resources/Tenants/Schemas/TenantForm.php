<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\CountryPacks\CountryPackRegistry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        $countries = array_combine(app(CountryPackRegistry::class)->installed(), app(CountryPackRegistry::class)->installed());

        return $schema->components([
            Section::make('Agency')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(63)
                    ->regex('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/')
                    ->notIn([config('platform.admin_subdomain'), 'www', 'api', 'mail'])
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Becomes {slug}.'.config('platform.base_domain').'. Cannot be changed later.'),
                Select::make('country_code')->options($countries)->required()->default('CM'),
                Select::make('default_locale')->options(['fr' => 'Français', 'en' => 'English'])->required()->default('fr'),
                Select::make('status')->options(['active' => 'Active', 'suspended' => 'Suspended', 'closed' => 'Closed'])->default('active')->required(),
                TextInput::make('plan')->default('starter')->required()->maxLength(40),
            ]),
            Section::make('First owner')->columns(2)->visibleOn('create')->schema([
                TextInput::make('owner_name')->required()->maxLength(120),
                TextInput::make('owner_email')->email()->required()->maxLength(190)
                    ->helperText('Gets an email link to set their own password.'),
            ]),
        ]);
    }
}
