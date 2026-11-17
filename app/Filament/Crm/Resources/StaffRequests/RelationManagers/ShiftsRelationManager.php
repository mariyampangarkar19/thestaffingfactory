<?php

namespace App\Filament\Crm\Resources\StaffRequests\RelationManagers;

use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use App\Models\StaffRequestPosition;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Shifts of one request. Columns, form and the "assign" button come from ShiftResource so they exist once.
 */
class ShiftsRelationManager extends RelationManager
{
    protected static string $relationship = 'shifts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Shifts');
    }

    public function isReadOnly(): bool
    {
        return ! $this->getOwnerRecord()->status->isOpen();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('staff_request_position_id')->label(__('Position'))->required()->columnSpanFull()
                ->options(fn () => $this->getOwnerRecord()->positions()->with('jobRole')->get()
                    ->mapWithKeys(fn (StaffRequestPosition $p) => [$p->id => $p->quantity.' × '.$p->jobRole->name()])),
            ...ShiftResource::scheduleFields($this->getOwnerRecord()->location),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => ShiftResource::withStaffing($query))
            ->defaultSort('starts_at')
            ->columns(ShiftResource::columns(withRequest: false))
            ->headerActions([CreateAction::make()->label(__('Add shift'))])
            ->recordActions([
                ShiftResource::assignAction(),
                Action::make('open')->label(__('Who is on it'))->icon('heroicon-o-users')
                    ->url(fn (Shift $record) => ShiftResource::getUrl('view', ['record' => $record])),
                EditAction::make()->after(fn (Shift $record) => ShiftResource::notifyScheduleChange($record)),
            ])
            ->emptyStateHeading(__('No shifts yet'))
            ->emptyStateDescription(__('Add the positions first, then create a shift for each date.'));
    }
}
