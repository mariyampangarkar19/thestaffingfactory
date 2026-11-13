<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles;

use App\Filament\Crm\Resources\FreelancerProfiles\Pages\CreateFreelancerProfile;
use App\Filament\Crm\Resources\FreelancerProfiles\Pages\EditFreelancerProfile;
use App\Filament\Crm\Resources\FreelancerProfiles\Pages\ListFreelancerProfiles;
use App\Filament\Crm\Resources\FreelancerProfiles\Pages\ViewFreelancerProfile;
use App\Filament\Crm\Resources\FreelancerProfiles\Schemas\FreelancerProfileForm;
use App\Filament\Crm\Resources\FreelancerProfiles\Schemas\FreelancerProfileInfolist;
use App\Filament\Crm\Resources\FreelancerProfiles\Tables\FreelancerProfilesTable;
use App\Models\FreelancerProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FreelancerProfileResource extends Resource
{
    protected static ?string $model = FreelancerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('Freelancer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Freelancers');
    }

    public static function form(Schema $schema): Schema
    {
        return FreelancerProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FreelancerProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FreelancerProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFreelancerProfiles::route('/'),
            'create' => CreateFreelancerProfile::route('/create'),
            'view' => ViewFreelancerProfile::route('/{record}'),
            'edit' => EditFreelancerProfile::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
