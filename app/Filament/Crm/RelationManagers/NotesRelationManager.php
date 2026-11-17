<?php

namespace App\Filament\Crm\RelationManagers;

use App\Enums\NoteKind;
use App\Models\Note;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal notes and the log of calls and emails. Reused on customers, freelancers and requests.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Notes and contact history');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('notes.view') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('kind')->label(__('Type'))->options(NoteKind::class)->default(NoteKind::Note)->required(),
            DateTimePicker::make('occurred_at')->label(__('When'))->seconds(false)->maxDate(now()->addMinute()),
            Textarea::make('body')->label(__('Details'))->required()->maxLength(5000)->rows(4)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('kind')->label(__('Type'))->badge(),
                TextColumn::make('body')->label(__('Details'))->wrap()->limit(240),
                TextColumn::make('author.name')->label(__('By'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('Added'))->since()->dateTimeTooltip(),
            ])
            ->headerActions([
                CreateAction::make()->label(__('Add note'))
                    ->mutateDataUsing(fn (array $data) => $data)
                    ->after(fn (Note $record) => $record->forceFill(['created_by' => auth()->id()])->save()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading(__('No notes yet'));
    }
}
