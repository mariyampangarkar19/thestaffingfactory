<?php

namespace App\Filament\Crm\Resources\JobRoles\RelationManagers;

use App\CountryPacks\CountryPackRegistry;
use App\Models\Client;
use App\Tenancy\TenantContext;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Hourly pay for the position. A rate for one customer overrides the general rate; the newest effective date wins.
 */
class PayRatesRelationManager extends RelationManager
{
    protected static string $relationship = 'payRates';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Pay rates');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('finance.view') ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('hourly_rate')->label(__('Pay per hour'))->numeric()->minValue(0)->maxValue(9999999)->required(),
            TextInput::make('currency')->label(__('Currency'))->required()->length(3)
                ->default(fn () => app(CountryPackRegistry::class)->get(app(TenantContext::class)->require()->country_code)->currency())
                ->dehydrateStateUsing(fn (string $state) => strtoupper($state)),
            DatePicker::make('effective_from')->label(__('Applies from'))->required()->default(today()),
            Select::make('client_id')->label(__('Only for this customer'))->searchable()
                ->options(fn () => Client::query()->orderBy('company_name')->limit(500)->pluck('company_name', 'id'))
                ->helperText(__('Leave empty for the general rate.')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('hourly_rate')->label(__('Pay per hour'))->money(fn ($record) => $record->currency)->alignEnd(),
                TextColumn::make('client.company_name')->label(__('Customer'))->placeholder(__('All customers')),
                TextColumn::make('effective_from')->label(__('Applies from'))->date(),
            ])
            ->headerActions([CreateAction::make()->label(__('Add pay rate'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No pay rate set'))
            ->emptyStateDescription(__('Without a rate, assignments for this position cannot be paid.'));
    }
}
