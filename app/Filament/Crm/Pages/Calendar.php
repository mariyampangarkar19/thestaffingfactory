<?php

namespace App\Filament\Crm\Pages;

use App\Enums\ShiftStatus;
use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use App\Support\Calendar\MonthGrid;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Facades\FilamentTimezone;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Calendar view of shifts (quote section 1, "Assignments & Scheduling").
 */
class Calendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.crm.calendar';

    #[Url]
    public ?string $month = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('requests.view') ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Operations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    public function getTitle(): string
    {
        return __('Calendar');
    }

    public function showMonth(?string $month): void
    {
        $this->month = $month;
    }

    public function grid(): MonthGrid
    {
        $grid = new MonthGrid($this->month, FilamentTimezone::get());

        ShiftResource::withStaffing(Shift::query())
            ->whereBetween('starts_at', $grid->rangeUtc())
            ->where('status', '!=', ShiftStatus::Cancelled)
            ->orderBy('starts_at')
            ->limit(1000)
            ->get()
            ->each(function (Shift $shift) use ($grid): void {
                $label = $shift->position->jobRole->name().' · '.$shift->staffRequest->client->company_name;

                $grid->add(
                    $shift->starts_at,
                    $shift->ends_at,
                    $label.' ('.$shift->staffed_count.'/'.$shift->headcount.')',
                    ShiftResource::getUrl('view', ['record' => $shift]),
                    match (true) {
                        $shift->status === ShiftStatus::Completed || $shift->ends_at->isPast() => 'is-done',
                        $shift->staffed_count < $shift->headcount => 'is-understaffed',
                        default => '',
                    },
                    $label,
                );
            });

        return $grid;
    }
}
