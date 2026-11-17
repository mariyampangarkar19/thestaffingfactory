<?php

namespace App\Filament\Portal\Pages;

use App\Actions\Timesheets\SubmitTimesheet;
use App\Enums\AssignmentStatus;
use App\Enums\TimesheetStatus;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Filament\Support\RunsDomainActions;
use App\Models\Assignment;
use App\Support\Duration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Timesheets (quote section 2): submit worked hours for each shift, then follow the agency's decision.
 */
class MyTimesheets extends Page implements HasTable
{
    use InteractsWithTable;
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'timesheets';

    public static function getNavigationLabel(): string
    {
        return __('Timesheets');
    }

    public function getTitle(): string
    {
        return __('Timesheets');
    }

    public static function getNavigationBadge(): ?string
    {
        $profileId = auth()->user()?->freelancerProfile()->value('id');
        $count = $profileId ? self::awaitingHours($profileId)->count() : 0;

        return $count > 0 ? (string) $count : null;
    }

    /** Shifts that have started, were not cancelled, and where hours are still missing or were rejected. */
    public static function awaitingHours(string $profileId): Builder
    {
        return self::worked($profileId)->where(fn ($q) => $q
            ->whereDoesntHave('timesheet')
            ->orWhereHas('timesheet', fn ($t) => $t->where('status', TimesheetStatus::Rejected)));
    }

    private static function worked(string $profileId): Builder
    {
        return MyAssignments::ownAssignments($profileId)
            ->where('shifts.starts_at', '<=', now())
            ->whereIn('assignments.status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => self::worked(self::freelancer()->id))
            ->defaultSort('shifts.starts_at', 'desc')
            ->columns([
                ...array_slice(MyAssignments::columns(), 0, 2),
                TextColumn::make('timesheet.submitted_minutes')->label(__('Hours submitted'))->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => Duration::format($state)),
                TextColumn::make('timesheet.approved_minutes')->label(__('Hours approved'))->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => Duration::format($state)),
                TextColumn::make('timesheet.status')->label(__('Status'))->badge()->placeholder(__('Hours not submitted')),
                TextColumn::make('timesheet.review_note')->label(__('Note from the agency'))->placeholder('—')->wrap(),
            ])
            ->recordActions([$this->submitAction()])
            ->emptyStateHeading(__('No shifts worked yet'))
            ->emptyStateDescription(__('After each shift, come back here to submit your hours.'));
    }

    private function submitAction(): Action
    {
        return Action::make('submitHours')
            ->label(fn (Assignment $record) => $record->timesheet ? __('Send again') : __('Submit hours'))
            ->icon('heroicon-o-paper-airplane')->button()
            ->visible(fn (Assignment $record) => $record->timesheet === null || $record->timesheet->status === TimesheetStatus::Rejected)
            ->modalHeading(__('Hours worked'))
            ->modalDescription(__('Enter the real start and end times. The agency checks them before payment.'))
            ->fillForm(fn (Assignment $record) => [
                'started_at' => $record->shift->starts_at,
                'ended_at' => min($record->shift->ends_at, now()),
                'break_minutes' => $record->shift->break_minutes,
            ])
            ->schema([
                DateTimePicker::make('started_at')->label(__('Started'))->seconds(false)->required(),
                DateTimePicker::make('ended_at')->label(__('Finished'))->seconds(false)->required()->after('started_at'),
                TextInput::make('break_minutes')->label(__('Break (minutes)'))->numeric()->integer()->minValue(0)->maxValue(240)->default(0)->required(),
                Textarea::make('note')->label(__('Note for the agency'))->maxLength(1000)->rows(2),
            ])
            ->action(function (Assignment $record, array $data): void {
                // Re-read through the owner-scoped query: the record id comes from the browser.
                $assignment = self::worked(self::freelancer()->id)->whereKey($record->getKey())->firstOrFail();

                RunsDomainActions::run(fn () => app(SubmitTimesheet::class)->handle(
                    $assignment,
                    Carbon::parse($data['started_at']),
                    Carbon::parse($data['ended_at']),
                    (int) $data['break_minutes'],
                    $data['note'] ?? null,
                ), __('Hours submitted. The agency will review them.'));
            });
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }
}
