<?php

namespace App\Filament\Crm\Resources\Clients;

use App\Enums\ClientStatus;
use App\Enums\Role;
use App\Filament\Crm\RelationManagers\DocumentsRelationManager;
use App\Filament\Crm\RelationManagers\NotesRelationManager;
use App\Filament\Crm\Resources\BaseResource;
use App\Models\Client;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Customer Management (quote section 1). Customers are records only; they never log in.
 */
class ClientResource extends BaseResource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('People');
    }

    public static function getModelLabel(): string
    {
        return __('Customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Company'))->columns(2)->schema([
                TextInput::make('company_name')->label(__('Company name'))->required()->maxLength(150),
                TextInput::make('sector')->label(__('Sector'))->maxLength(60)
                    ->datalist(['Hotel', 'Restaurant', 'Events', 'Retail', 'Corporate']),
                TextInput::make('email')->label(__('Email'))->email()->maxLength(190),
                TextInput::make('phone')->label(__('Phone'))->tel()->maxLength(32),
                TextInput::make('city')->label(__('City'))->maxLength(100),
                TextInput::make('address')->label(__('Address'))->maxLength(255),
                TextInput::make('tax_number')->label(__('Tax number'))->maxLength(60),
                Select::make('status')->label(__('Status'))->options(ClientStatus::class)->default(ClientStatus::Lead)->required(),
                Select::make('account_manager_id')->label(__('Account manager'))
                    ->options(fn () => User::role(Role::staffValues())->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Company'))->columns(3)->columnSpanFull()->schema([
                TextEntry::make('company_name')->label(__('Company name')),
                TextEntry::make('sector')->label(__('Sector'))->placeholder('—'),
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('email')->label(__('Email'))->placeholder('—')->copyable(),
                TextEntry::make('phone')->label(__('Phone'))->placeholder('—')->copyable(),
                TextEntry::make('city')->label(__('City'))->placeholder('—'),
                TextEntry::make('address')->label(__('Address'))->placeholder('—'),
                TextEntry::make('tax_number')->label(__('Tax number'))->placeholder('—'),
                TextEntry::make('accountManager.name')->label(__('Account manager'))->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('company_name')->label(__('Company name'))->searchable()->sortable()->weight('medium'),
                TextColumn::make('sector')->label(__('Sector'))->placeholder('—')->toggleable(),
                TextColumn::make('city')->label(__('City'))->searchable()->placeholder('—'),
                TextColumn::make('phone')->label(__('Phone'))->searchable()->placeholder('—'),
                TextColumn::make('staff_requests_count')->counts('staffRequests')->label(__('Requests'))->alignEnd(),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('created_at')->label(__('Added'))->date()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(ClientStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->emptyStateHeading(__('No customers yet'))
            ->emptyStateDescription(__('Customers are added here or arrive with a website request.'));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\RequestsRelationManager::class,
            NotesRelationManager::class,
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
