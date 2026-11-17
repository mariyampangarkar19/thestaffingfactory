<?php

namespace App\Filament\Crm\Resources\Shifts;

use App\Actions\Scheduling\AssignFreelancerToShift;
use App\Enums\AssignmentStatus;
use App\Enums\FreelancerStatus;
use App\Enums\ShiftStatus;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\FreelancerProfile;
use App\Models\Shift;
use App\Notifications\AssignmentNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use UnitEnum;

/**
 * Assignments & Scheduling (quote section 1): shifts across all requests, who is on them, and assigning freelancers.
 */
class ShiftResource extends BaseResource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Shift');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Shifts and assignments');
    }

    public static function canCreate(): bool
    {
        return false; // Shifts are created from their staffing request.
    }

    public static function getEloquentQuery(): Builder
    {
        return self::withStaffing(parent::getEloquentQuery());
    }

    /** Eager-loads what the columns need and counts the active assignments as "staffed". */
    public static function withStaffing(Builder|Relation $query): Builder|Relation
    {
        return $query->with(['position.jobRole', 'staffRequest.client'])
            ->withCount(['assignments as staffed_count' => fn ($q) => $q->whereIn('status', [
                AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed,
            ])]);
    }

    /** @return list<Component> */
    public static function scheduleFields(?string $defaultLocation = null): array
    {
        return [
            DateTimePicker::make('starts_at')->label(__('Starts'))->seconds(false)->required(),
            DateTimePicker::make('ends_at')->label(__('Ends'))->seconds(false)->required()->after('starts_at'),
            TextInput::make('headcount')->label(__('People needed'))->numeric()->integer()->minValue(1)->maxValue(500)->default(1)->required(),
            TextInput::make('break_minutes')->label(__('Break (minutes)'))->numeric()->integer()->minValue(0)->maxValue(240)->default(0),
            TextInput::make('location')->label(__('Location'))->maxLength(255)->default($defaultLocation)->columnSpanFull(),
            Select::make('status')->label(__('Status'))->options(ShiftStatus::class)->default(ShiftStatus::Planned)->required(),
            Textarea::make('notes')->label(__('Notes for the team'))->maxLength(1000)->columnSpanFull(),
        ];
    }

    /** @return list<TextColumn> */
    public static function columns(bool $withRequest = true): array
    {
        return array_values(array_filter([
            TextColumn::make('starts_at')->label(__('Date'))->dateTime('D j M Y, H:i')->sortable()
                ->description(fn (Shift $record) => __('until :time', ['time' => $record->ends_at->timezone(FilamentTimezone::get())->format('H:i')])),
            $withRequest ? TextColumn::make('staffRequest.reference')->label(__('Request'))
                ->description(fn (Shift $record) => $record->staffRequest->client->company_name)->searchable() : null,
            TextColumn::make('position')->label(__('Position'))->formatStateUsing(fn (Shift $record) => $record->position->jobRole->name()),
            TextColumn::make('location')->label(__('Location'))->limit(30)->placeholder('—')->toggleable(),
            TextColumn::make('staffed_count')->label(__('Staffed'))
                ->formatStateUsing(fn (Shift $record) => $record->staffed_count.' / '.$record->headcount)
                ->badge()->color(fn (Shift $record) => $record->staffed_count >= $record->headcount ? 'success' : 'warning'),
            TextColumn::make('status')->label(__('Status'))->badge(),
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns(self::columns())
            ->filters([
                Filter::make('upcoming')->label(__('Upcoming only'))->default()
                    ->query(fn (Builder $query) => $query->where('ends_at', '>=', now())),
                Filter::make('understaffed')->label(__('Still needs people'))
                    ->query(fn (Builder $query) => $query->whereRaw('(select count(*) from assignments a where a.shift_id = shifts.id and a.status in (?, ?, ?)) < shifts.headcount', ['assigned', 'confirmed', 'completed'])),
                SelectFilter::make('status')->label(__('Status'))->options(ShiftStatus::class),
            ])
            ->recordActions([self::assignAction(), ViewAction::make()->label(__('Who is on it'))])
            ->emptyStateHeading(__('No shifts to show'))
            ->emptyStateDescription(__('Shifts are created from a staffing request.'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (Shift $record) => $record->position->jobRole->name().' · '.$record->staffRequest->client->company_name)->columns(3)->columnSpanFull()->schema([
                TextEntry::make('starts_at')->label(__('Starts'))->dateTime('D j M Y, H:i'),
                TextEntry::make('ends_at')->label(__('Ends'))->dateTime('D j M Y, H:i'),
                TextEntry::make('status')->label(__('Status'))->badge(),
                TextEntry::make('staffRequest.reference')->label(__('Request')),
                TextEntry::make('location')->label(__('Location'))->placeholder('—'),
                TextEntry::make('staffed_count')->label(__('Staffed'))->formatStateUsing(fn (Shift $record) => $record->staffed_count.' / '.$record->headcount),
                TextEntry::make('notes')->label(__('Notes for the team'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    /** "Assign freelancer": offers approved freelancers who do this position; the action re-checks every rule. */
    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('Assign freelancer'))->icon('heroicon-o-user-plus')
            ->visible(fn (Shift $record) => auth()->user()->can('update', $record)
                && ! in_array($record->status, [ShiftStatus::Completed, ShiftStatus::Cancelled], true))
            ->schema(fn (Shift $record) => [
                Select::make('freelancer_profile_ids')->label(__('Freelancers'))->multiple()->required()->searchable()
                    ->options(fn () => self::candidates($record))
                    ->helperText(__('Approved freelancers for this position. People already on the shift are not listed.')),
            ])
            ->action(function (Shift $record, array $data): void {
                $assigned = 0;
                foreach (FreelancerProfile::query()->whereIn('id', $data['freelancer_profile_ids'])->get() as $freelancer) {
                    $assigned += (int) RunsDomainActions::run(
                        fn () => app(AssignFreelancerToShift::class)->handle($record, $freelancer, auth()->user()),
                    );
                }

                if ($assigned > 0) {
                    Notification::make()->success()
                        ->title(trans_choice('{1} :count freelancer assigned|[2,*] :count freelancers assigned', $assigned, ['count' => $assigned]))->send();
                }
            });
    }

    /** @return array<string, string> */
    private static function candidates(Shift $shift): array
    {
        return FreelancerProfile::query()
            ->where('status', FreelancerStatus::Approved)
            ->whereHas('jobRoles', fn ($q) => $q->whereKey($shift->position->job_role_id))
            ->whereDoesntHave('assignments', fn ($q) => $q->where('shift_id', $shift->id)
                ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed]))
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (FreelancerProfile $f) => [$f->id => $f->fullName().($f->city ? ' · '.$f->city : '')])
            ->all();
    }

    /** Tell everyone on a shift when its date or time was edited. */
    public static function notifyScheduleChange(Shift $shift): void
    {
        if (! $shift->wasChanged(['starts_at', 'ends_at', 'location'])) {
            return;
        }

        $shift->assignments()->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed])->get()
            ->each(fn ($assignment) => app(AssignmentNotifier::class)->scheduleChanged($assignment));
    }

    public static function getRelations(): array
    {
        return [RelationManagers\AssignmentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShifts::route('/'),
            'view' => Pages\ViewShift::route('/{record}'),
        ];
    }
}
