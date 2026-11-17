<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\RelationManagers;

use App\Models\FreelancerUnavailability;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Days the freelancer cannot work. The assign action refuses a shift that falls on one of them. */
class UnavailabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'unavailabilities';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Unavailable dates');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('starts_on')->label(__('From'))->required(),
            DatePicker::make('ends_on')->label(__('To'))->required()->afterOrEqual('starts_on'),
            TextInput::make('reason')->label(__('Reason'))->maxLength(255)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('starts_on')->label(__('From'))->date(),
                TextColumn::make('ends_on')->label(__('To'))->date(),
                TextColumn::make('reason')->label(__('Reason'))->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add dates'))
                    ->after(fn (FreelancerUnavailability $record) => $record->forceFill(['created_by' => auth()->id()])->save()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No unavailable dates'));
    }
}
