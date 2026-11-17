<?php

namespace App\Filament\Crm\Resources\Payments\RelationManagers;

use App\Models\PaymentItem;
use App\Support\Duration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The approved timesheets a payment covers. Read-only: the lines are fixed when the payment is created. */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Hours covered');
    }

    public function table(Table $table): Table
    {
        $currency = fn () => $this->getOwnerRecord()->currency;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['timesheet.assignment.shift.position.jobRole', 'timesheet.assignment.shift.staffRequest.client']))
            ->columns([
                TextColumn::make('timesheet.started_at')->label(__('Date'))->date('D j M Y'),
                TextColumn::make('customer')->label(__('Customer'))
                    ->state(fn (PaymentItem $record) => $record->timesheet->assignment->shift->staffRequest->client->company_name),
                TextColumn::make('position')->label(__('Position'))
                    ->state(fn (PaymentItem $record) => $record->timesheet->assignment->shift->position->jobRole->name()),
                TextColumn::make('minutes')->label(__('Hours'))->alignEnd()->formatStateUsing(fn (int $state) => Duration::format($state)),
                TextColumn::make('hourly_rate')->label(__('Pay per hour'))->money($currency)->alignEnd(),
                TextColumn::make('amount')->label(__('Amount'))->money($currency)->alignEnd(),
            ])
            ->paginated(false);
    }
}
