<?php

namespace App\Filament\Portal\Pages;

use App\Enums\AssignmentStatus;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Models\Assignment;
use App\Support\Calendar\MonthGrid;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Facades\FilamentTimezone;
use Livewire\Attributes\Url;

/** My Calendar (quote section 2): the freelancer's own shifts by month. */
class MyCalendar extends Page
{
    use ResolvesFreelancer;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'calendar';

    protected string $view = 'filament.portal.calendar';

    #[Url]
    public ?string $month = null;

    public static function getNavigationLabel(): string
    {
        return __('My calendar');
    }

    public function getTitle(): string
    {
        return __('My calendar');
    }

    public function showMonth(?string $month): void
    {
        $this->month = $month;
    }

    public function grid(): MonthGrid
    {
        $grid = new MonthGrid($this->month, FilamentTimezone::get());

        MyAssignments::ownAssignments(self::freelancer()->id)
            ->whereBetween('shifts.starts_at', $grid->rangeUtc())
            ->whereIn('assignments.status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed])
            ->orderBy('shifts.starts_at')
            ->get()
            ->each(fn (Assignment $a) => $grid->add(
                $a->shift->starts_at,
                $a->shift->ends_at,
                $a->shift->position->jobRole->name().' · '.$a->shift->staffRequest->client->company_name,
                null,
                $a->shift->ends_at->isPast() ? 'is-done' : '',
            ));

        return $grid;
    }
}
