<?php

namespace App\Filament\Crm\Widgets;

use App\Enums\ShiftStatus;
use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingShifts extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('requests.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Next shifts'))
            ->query(fn () => ShiftResource::withStaffing(Shift::query())
                ->where('ends_at', '>=', now())
                ->where('status', '!=', ShiftStatus::Cancelled)
                ->orderBy('starts_at'))
            ->columns(ShiftResource::columns())
            ->recordUrl(fn (Shift $record) => ShiftResource::getUrl('view', ['record' => $record]))
            ->paginated([5])
            ->emptyStateHeading(__('No upcoming shifts'))
            ->emptyStateDescription(__('Shifts are created from a staffing request.'));
    }
}
