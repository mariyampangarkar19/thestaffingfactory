<?php

namespace App\Filament\Portal\Widgets;

use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Filament\Portal\Pages\MyAssignments;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NextAssignments extends TableWidget
{
    use ResolvesFreelancer;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Your next assignments'))
            ->query(fn () => MyAssignments::ownAssignments(self::freelancer()->id)->where('shifts.ends_at', '>=', now())->orderBy('shifts.starts_at'))
            ->columns(MyAssignments::columns())
            ->paginated([5])
            ->emptyStateHeading(__('No upcoming assignments'))
            ->emptyStateDescription(__('The agency will notify you here when you are assigned to a shift.'));
    }
}
