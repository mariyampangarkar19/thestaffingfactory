<?php

namespace App\Filament\Crm\Resources\Clients\RelationManagers;

use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use App\Models\StaffRequest;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer's staffing requests and history (read-only here; managed under Staffing requests).
 */
class RequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'staffRequests';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Staffing requests');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('Reference'))->weight('medium'),
                TextColumn::make('title')->label(__('Title'))->limit(50),
                TextColumn::make('starts_on')->label(__('Start'))->date()->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge(),
            ])
            ->recordActions([
                Action::make('open')->label(__('Open'))->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StaffRequest $record) => StaffRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading(__('No requests yet'));
    }
}
