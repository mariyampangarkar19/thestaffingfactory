<?php

namespace App\Reports;

use App\Enums\TimesheetStatus;
use App\Models\Timesheet;
use App\Reports\Contracts\Report;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class HoursReport implements Report
{
    public function title(): string
    {
        return __('Hours worked');
    }

    public function description(): string
    {
        return __('Approved hours per freelancer for the period, with the number of shifts.');
    }

    public function permission(): string
    {
        return 'reports.view';
    }

    public function headings(): array
    {
        return [__('Freelancer'), __('Phone'), __('Shifts'), __('Hours approved')];
    }

    public function rows(CarbonInterface $from, CarbonInterface $to): iterable
    {
        $rows = Timesheet::query()
            ->join('freelancer_profiles as f', 'f.id', '=', 'timesheets.freelancer_profile_id')
            ->whereIn('timesheets.status', [TimesheetStatus::Approved, TimesheetStatus::Adjusted])
            ->whereBetween('timesheets.started_at', [$from, $to])
            ->groupBy('f.id', 'f.first_name', 'f.last_name', 'f.phone')
            ->orderBy('f.first_name')->orderBy('f.last_name')
            ->get(['f.first_name', 'f.last_name', 'f.phone', DB::raw('count(*) as shifts'), DB::raw('sum(timesheets.approved_minutes) as minutes')]);

        foreach ($rows as $row) {
            yield [trim($row->first_name.' '.$row->last_name), $row->phone, (int) $row->shifts, Duration::decimalHours((int) $row->minutes)];
        }
    }
}
