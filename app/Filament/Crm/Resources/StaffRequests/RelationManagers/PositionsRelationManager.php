<?php

namespace App\Filament\Crm\Resources\StaffRequests\RelationManagers;

use App\Models\JobRole;
use App\Models\StaffRequestPosition;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Positions needed');
    }

    public function isReadOnly(): bool
    {
        return ! $this->getOwnerRecord()->status->isOpen();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('job_role_id')->label(__('Position'))->required()
                ->options(fn () => JobRole::query()->where('is_active', true)->get()->mapWithKeys(fn (JobRole $r) => [$r->id => $r->name()])),
            TextInput::make('quantity')->label(__('How many'))->numeric()->integer()->minValue(1)->maxValue(500)->default(1)->required(),
            TextInput::make('pay_rate')->label(__('Pay per hour'))->numeric()->minValue(0)->maxValue(9999999)
                ->helperText(__('Leave empty to use the standard rate.')),
            Textarea::make('notes')->label(__('Notes'))->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('jobRole'))
            ->columns([
                TextColumn::make('jobRole')->label(__('Position'))->formatStateUsing(fn (StaffRequestPosition $record) => $record->jobRole->name())->weight('medium'),
                TextColumn::make('quantity')->label(__('How many'))->alignEnd(),
                TextColumn::make('pay_rate')->label(__('Pay per hour'))->money(fn (StaffRequestPosition $record) => $record->currency)->placeholder(__('Standard rate'))->alignEnd(),
                TextColumn::make('notes')->label(__('Notes'))->limit(60)->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label(__('Add position'))])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No positions yet'));
    }
}
