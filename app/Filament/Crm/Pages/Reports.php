<?php

namespace App\Filament\Crm\Pages;

use App\Reports\Contracts\Report;
use App\Reports\ReportRegistry;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Reports & Exports (quote section 1). Choose a period, then download Excel or open the print view (PDF).
 */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.crm.reports';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Records');
    }

    public static function getNavigationLabel(): string
    {
        return __('Reports and exports');
    }

    public function getTitle(): string
    {
        return __('Reports and exports');
    }

    public function mount(): void
    {
        $today = Carbon::now(FilamentTimezone::get());
        $this->from = $today->copy()->startOfMonth()->toDateString();
        $this->to = $today->toDateString();
    }

    /** @return array<string, string> */
    protected function rules(): array
    {
        return ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'];
    }

    public function updated(): void
    {
        $this->validate();
    }

    /** @return array<string, Report> */
    public function reports(): array
    {
        return app(ReportRegistry::class)->availableTo(auth()->user());
    }

    public function exportUrl(string $report, string $format): string
    {
        return route('reports.export', ['report' => $report, 'from' => $this->from, 'to' => $this->to, 'format' => $format]);
    }
}
