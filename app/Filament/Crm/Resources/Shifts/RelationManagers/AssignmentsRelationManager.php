<?php

namespace App\Filament\Crm\Resources\Shifts\RelationManagers;

use App\Actions\Scheduling\CancelAssignment;
use App\Enums\AssignmentStatus;
use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\Assignment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Assigned freelancers');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['freelancerProfile', 'assignedBy', 'timesheet']))
            ->columns([
                TextColumn::make('freelancerProfile')->label(__('Freelancer'))->weight('medium')
                    ->formatStateUsing(fn (Assignment $record) => $record->freelancerProfile->fullName())
                    ->description(fn (Assignment $record) => $record->freelancerProfile->phone),
                TextColumn::make('pay_rate')->label(__('Pay per hour'))->money(fn (Assignment $record) => $record->currency)->placeholder(__('Not set'))->alignEnd(),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('timesheet.status')->label(__('Timesheet'))->badge()->placeholder(__('Not submitted')),
                TextColumn::make('assignedBy.name')->label(__('Assigned by'))->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                ShiftResource::assignAction()->record(fn () => $this->getOwnerRecord()),
            ])
            ->recordActions([
                $this->statusAction('remove', __('Remove from shift'), AssignmentStatus::Cancelled, 'heroicon-o-user-minus'),
                $this->statusAction('no_show', __('Mark as no-show'), AssignmentStatus::NoShow, 'heroicon-o-exclamation-triangle'),
            ])
            ->emptyStateHeading(__('Nobody assigned yet'));
    }

    private function statusAction(string $name, string $label, AssignmentStatus $status, string $icon): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color('danger')->requiresConfirmation()
            ->visible(fn (Assignment $record) => in_array($record->status, [AssignmentStatus::Assigned, AssignmentStatus::Confirmed], true)
                && auth()->user()->can('update', $record))
            ->action(fn (Assignment $record) => RunsDomainActions::run(
                fn () => app(CancelAssignment::class)->handle($record, $status),
                __('Assignment updated'),
            ));
    }
}
