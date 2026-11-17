<?php

namespace App\Reports;

use App\Models\FreelancerProfile;
use App\Reports\Contracts\Report;
use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;

final class FreelancersReport implements Report
{
    public function title(): string
    {
        return __('Freelancers');
    }

    public function description(): string
    {
        return __('Freelancers who registered in the period, with positions, quiz result and status.');
    }

    public function permission(): string
    {
        return 'reports.view';
    }

    public function headings(): array
    {
        return [__('Name'), __('Phone'), __('Email'), __('City'), __('Positions'), __('Quiz score'), __('Category'), __('Status'), __('Availability'), __('Source'), __('Signed up')];
    }

    public function rows(CarbonInterface $from, CarbonInterface $to): iterable
    {
        $tz = FilamentTimezone::get();

        $query = FreelancerProfile::query()->with(['jobRoles', 'latestQuizResult'])
            ->whereBetween('created_at', [$from, $to])->orderBy('created_at')->orderBy('id');

        foreach ($query->lazy(500) as $f) {
            yield [
                $f->fullName(),
                $f->phone,
                $f->email,
                $f->city,
                $f->jobRoles->map->name()->implode(', '),
                $f->latestQuizResult?->score,
                $f->latestQuizResult?->grade,
                $f->status->getLabel(),
                $f->availability_status->getLabel(),
                $f->source,
                $f->created_at->timezone($tz)->format('Y-m-d'),
            ];
        }
    }
}
