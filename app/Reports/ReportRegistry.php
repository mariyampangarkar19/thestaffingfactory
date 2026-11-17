<?php

namespace App\Reports;

use App\Models\User;
use App\Reports\Contracts\Report;

/**
 * The reports the platform offers, keyed by the name used in the export address. Configured in config/platform.php.
 */
final class ReportRegistry
{
    /** @param array<string, class-string<Report>> $reports */
    public function __construct(private readonly array $reports) {}

    public function find(string $key): ?Report
    {
        return isset($this->reports[$key]) ? app($this->reports[$key]) : null;
    }

    /** @return array<string, Report> the reports this person may run */
    public function availableTo(User $user): array
    {
        return collect($this->reports)
            ->map(fn (string $class) => app($class))
            ->filter(fn (Report $report) => $user->can('reports.view') && $user->can($report->permission()))
            ->all();
    }
}
