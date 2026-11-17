<?php

namespace App\Filament\Crm\Resources\Timesheets;

use App\Actions\Timesheets\ReviewTimesheet;
use App\Enums\TimesheetStatus;
use App\Filament\Crm\Resources\BaseResource;
use App\Filament\Support\RunsDomainActions;
use App\Models\Timesheet;
use App\Support\Duration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Timesheets (quote section 1): hours submitted by freelancers, approved, adjusted or rejected by the agency.
 */
class TimesheetResource extends BaseResource
{
    protected static ?string $model = Timesheet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Finance');
    }

    public static function getModelLabel(): string
    {
        return __('Timesheet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Timesheets');
    }

    public static function canCreate(): bool
    {
        return false; // Hours come from the freelancer portal.
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = Timesheet::query()->where('status', TimesheetStatus::Submitted)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Timesheets to review');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'freelancerProfile', 'reviewer', 'assignment.shift.position.jobRole', 'assignment.shift.staffRequest.client',
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('freelancerProfile.first_name')->label(__('Freelancer'))->weight('medium')
                    ->formatStateUsing(fn (Timesheet $record) => $record->freelancerProfile->fullName())
                    ->searchable(['freelancer_profiles.first_name', 'freelancer_profiles.last_name']),
                TextColumn::make('assignment.shift.starts_at')->label(__('Shift'))->dateTime('D j M Y')
                    ->description(fn (Timesheet $record) => $record->assignment->shift->position->jobRole->name()
                        .' · '.$record->assignment->shift->staffRequest->client->company_name),
                TextColumn::make('started_at')->label(__('Worked'))->dateTime('H:i')
                    ->description(fn (Timesheet $record) => __('until :time', ['time' => $record->ended_at->timezone(FilamentTimezone::get())->format('H:i')])),
                TextColumn::make('submitted_minutes')->label(__('Hours submitted'))->alignEnd()
                    ->formatStateUsing(fn (int $state) => Duration::format($state)),
                TextColumn::make('approved_minutes')->label(__('Hours approved'))->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => Duration::format($state)),
                TextColumn::make('status')->label(__('Status'))->badge(),
                TextColumn::make('freelancer_note')->label(__('Freelancer note'))->limit(40)->placeholder('—')->toggleable(),
                TextColumn::make('review_note')->label(__('Review note'))->limit(40)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewer.name')->label(__('Reviewed by'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('Status'))->options(TimesheetStatus::class)->default(TimesheetStatus::Submitted->value),
            ])
            ->recordActions(self::reviewActions())
            ->emptyStateHeading(__('No timesheets to show'))
            ->emptyStateDescription(__('Freelancers submit their hours in the portal after each shift.'));
    }

    /** @return list<Action> */
    public static function reviewActions(): array
    {
        $open = fn (Timesheet $record) => $record->status === TimesheetStatus::Submitted && auth()->user()->can('update', $record);

        return [
            Action::make('approve')->label(__('Approve'))->icon('heroicon-o-check')->color('success')
                ->visible($open)
                ->requiresConfirmation()
                ->modalDescription(fn (Timesheet $record) => __(':hours will be approved for payment.', ['hours' => Duration::format($record->submitted_minutes)]))
                ->action(fn (Timesheet $record) => RunsDomainActions::run(
                    fn () => app(ReviewTimesheet::class)->approve($record, auth()->user()),
                    __('Timesheet approved'),
                )),
            Action::make('adjust')->label(__('Adjust'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible($open)
                ->schema(fn (Timesheet $record) => [
                    TextInput::make('hours')->label(__('Hours'))->numeric()->integer()->minValue(0)->maxValue(16)->required()
                        ->default(intdiv($record->submitted_minutes, 60)),
                    TextInput::make('minutes')->label(__('Minutes'))->numeric()->integer()->minValue(0)->maxValue(59)->required()
                        ->default($record->submitted_minutes % 60),
                    Textarea::make('note')->label(__('Reason shown to the freelancer'))->required()->maxLength(1000),
                ])
                ->action(fn (Timesheet $record, array $data) => RunsDomainActions::run(
                    fn () => app(ReviewTimesheet::class)->approve($record, auth()->user(), ((int) $data['hours']) * 60 + (int) $data['minutes'], $data['note']),
                    __('Timesheet adjusted and approved'),
                )),
            Action::make('reject')->label(__('Reject'))->icon('heroicon-o-x-mark')->color('danger')
                ->visible($open)
                ->schema([Textarea::make('note')->label(__('Reason shown to the freelancer'))->required()->maxLength(1000)])
                ->action(fn (Timesheet $record, array $data) => RunsDomainActions::run(
                    fn () => app(ReviewTimesheet::class)->reject($record, auth()->user(), $data['note']),
                    __('Timesheet rejected'),
                )),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTimesheets::route('/')];
    }
}
