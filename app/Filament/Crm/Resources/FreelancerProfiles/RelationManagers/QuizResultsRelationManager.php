<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Quiz scores sent by the website. Read-only: results are never edited by hand. */
class QuizResultsRelationManager extends RelationManager
{
    protected static string $relationship = 'quizResults';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Quiz results');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('completed_at', 'desc')
            ->columns([
                TextColumn::make('quiz_role')->label(__('Quiz')),
                TextColumn::make('score')->label(__('Score'))->suffix(' / 100')->alignEnd(),
                TextColumn::make('grade')->label(__('Category'))->badge()->placeholder('—'),
                IconColumn::make('passed')->label(__('Passed'))->boolean(),
                TextColumn::make('completed_at')->label(__('Taken'))->dateTime(),
            ])
            ->emptyStateHeading(__('No quiz taken yet'));
    }
}
