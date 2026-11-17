<?php

namespace App\Reports;

use App\Models\Assignment;
use App\Reports\Contracts\Report;
use App\Support\Duration;
use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;

final class AssignmentsReport implements Report
{
    public function title(): string
    {
        return __('Assignments');
    }

    public function description(): string
    {
        return __('Who worked where: every assignment with its shift, customer and status.');
    }

    public function permission(): string
    {
        return 'reports.view';
    }

    public function headings(): array
    {
        return [__('Date'), __('Starts'), __('Ends'), __('Request'), __('Customer'), __('Position'), __('Freelancer'), __('Phone'), __('Status'), __('Hours approved')];
    }

    public function rows(CarbonInterface $from, CarbonInterface $to): iterable
    {
        $tz = FilamentTimezone::get();

        $query = Assignment::query()
            ->select('assignments.*')
            ->join('shifts', 'shifts.id', '=', 'assignments.shift_id')
            ->whereBetween('shifts.starts_at', [$from, $to])
            ->orderBy('shifts.starts_at')->orderBy('assignments.id')
            ->with(['shift.position.jobRole', 'shift.staffRequest.client', 'freelancerProfile', 'timesheet']);

        foreach ($query->lazy(500) as $a) {
            yield [
                $a->shift->starts_at->timezone($tz)->format('Y-m-d'),
                $a->shift->starts_at->timezone($tz)->format('H:i'),
                $a->shift->ends_at->timezone($tz)->format('H:i'),
                $a->shift->staffRequest->reference,
                $a->shift->staffRequest->client->company_name,
                $a->shift->position->jobRole->name(),
                $a->freelancerProfile->fullName(),
                $a->freelancerProfile->phone,
                $a->status->getLabel(),
                $a->timesheet?->approved_minutes !== null ? Duration::decimalHours($a->timesheet->approved_minutes) : null,
            ];
        }
    }
}
