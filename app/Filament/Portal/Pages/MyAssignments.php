<?php

namespace App\Filament\Portal\Pages;

use App\Enums\AssignmentStatus;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Models\Assignment;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * My Assignments (quote section 2). The agency assigns; freelancers cannot browse or apply for work.
 */
class MyAssignments extends Page implements HasTable
{
    use InteractsWithTable;
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'assignments';

    public static function getNavigationLabel(): string
    {
        return __('My assignments');
    }

    public function getTitle(): string
    {
        return __('My assignments');
    }

    /** The signed-in freelancer's assignments joined to their shift, so lists can sort and filter by shift date. */
    public static function ownAssignments(string $profileId): Builder
    {
        return Assignment::query()
            ->select('assignments.*')
            ->join('shifts', 'shifts.id', '=', 'assignments.shift_id')
            ->where('assignments.freelancer_profile_id', $profileId)
            ->with(['shift.position.jobRole', 'shift.staffRequest.client', 'timesheet']);
    }

    /** @return list<TextColumn> */
    public static function columns(): array
    {
        return [
            TextColumn::make('shift.starts_at')->label(__('Date'))->dateTime('D j M Y')->weight('medium')
                ->description(fn (Assignment $record) => $record->shift->starts_at->timezone(FilamentTimezone::get())->format('H:i')
                    .' – '.$record->shift->ends_at->timezone(FilamentTimezone::get())->format('H:i')),
            TextColumn::make('position')->label(__('Position'))
                ->state(fn (Assignment $record) => $record->shift->position->jobRole->name())
                ->description(fn (Assignment $record) => $record->shift->staffRequest->client->company_name),
            TextColumn::make('shift.location')->label(__('Location'))->placeholder('—')->wrap()
                ->state(fn (Assignment $record) => $record->shift->location ?: $record->shift->staffRequest->location),
            TextColumn::make('status')->label(__('Status'))->badge(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => self::ownAssignments(self::freelancer()->id))
            ->defaultSort('shifts.starts_at', 'desc')
            ->columns([
                ...self::columns(),
                TextColumn::make('pay_rate')->label(__('Pay per hour'))->money(fn (Assignment $record) => $record->currency)->placeholder('—')->alignEnd(),
                TextColumn::make('shift.notes')->label(__('Instructions'))->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('when')->label(__('Show'))
                    ->options(['upcoming' => __('Upcoming'), 'past' => __('Past')])
                    ->default('upcoming')
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'upcoming' => $query->where('shifts.ends_at', '>=', now())->whereIn('assignments.status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed]),
                        'past' => $query->where('shifts.ends_at', '<', now()),
                        default => $query,
                    }),
            ])
            ->emptyStateHeading(__('No assignments to show'))
            ->emptyStateDescription(__('The agency will notify you here when you are assigned to a shift.'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
