<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\RelationManagers;

use App\Models\FreelancerEvaluation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Internal performance evaluations. Never shown in the freelancer portal. */
class EvaluationsRelationManager extends RelationManager
{
    protected static string $relationship = 'evaluations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Internal evaluations');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('rating')->label(__('Rating'))->required()
                ->options([5 => '5 · '.__('Excellent'), 4 => '4 · '.__('Good'), 3 => '3 · '.__('Fair'), 2 => '2 · '.__('Weak'), 1 => '1 · '.__('Poor')]),
            Textarea::make('comment')->label(__('Comment'))->maxLength(2000)->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')->label(__('Rating'))->suffix(' / 5'),
                TextColumn::make('comment')->label(__('Comment'))->wrap()->placeholder('—'),
                TextColumn::make('author.name')->label(__('By'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('Added'))->date(),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add evaluation'))
                    ->after(fn (FreelancerEvaluation $record) => $record->forceFill(['created_by' => auth()->id()])->save()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No evaluations yet'));
    }
}
