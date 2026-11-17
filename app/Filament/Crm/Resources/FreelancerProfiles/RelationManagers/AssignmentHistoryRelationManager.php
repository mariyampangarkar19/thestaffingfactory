<?php

namespace App\Filament\Crm\Resources\FreelancerProfiles\RelationManagers;

use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Models\Assignment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Where this freelancer has worked and is booked. Assigning is done from the shift. */
class AssignmentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Assignments');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('requests.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['shift.position.jobRole', 'shift.staffRequest.client', 'timesheet'])
                ->join('shifts', 'shifts.id', '=', 'assignments.shift_id')
                ->orderByDesc('shifts.starts_at')
                ->select('assignments.*'))
            ->columns([
                TextColumn::make('shift.starts_at')->label(__('Date'))->dateTime('D j M Y, H:i'),
                TextColumn::make('shift.staffRequest.client.company_name')->label(__('Customer')),
                TextColumn::make('position')->label(__('Position'))->state(fn (Assignment $record) => $record->shift->position->jobRole->name()),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('timesheet.status')->label(__('Timesheet'))->badge()->placeholder(__('Not submitted')),
            ])
            ->recordUrl(fn (Assignment $record) => ShiftResource::getUrl('view', ['record' => $record->shift_id]))
            ->emptyStateHeading(__('No assignments yet'));
    }
}
