<?php

namespace App\Filament\Crm\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\FreelancerStatus;
use App\Enums\RequestStatus;
use App\Enums\TimesheetStatus;
use App\Filament\Crm\Resources\Clients\ClientResource;
use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use App\Filament\Crm\Resources\Timesheets\TimesheetResource;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\StaffRequest;
use App\Models\Timesheet;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The numbers the quote lists for the dashboard. Each card shows only if the signed-in person may see that module.
 */
class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 10;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('freelancers.view')) {
            $waiting = FreelancerProfile::query()->whereIn('status', [FreelancerStatus::New, FreelancerStatus::Screening])->count();
            $stats[] = Stat::make(__('Approved freelancers'), FreelancerProfile::query()->where('status', FreelancerStatus::Approved)->count())
                ->description(trans_choice('{0} No registrations waiting|{1} :count registration waiting for review|[2,*] :count registrations waiting for review', $waiting, ['count' => $waiting]))
                ->color($waiting > 0 ? 'warning' : 'gray')
                ->url(FreelancerProfileResource::getUrl());
        }

        if ($user->can('clients.view')) {
            $stats[] = Stat::make(__('Customers'), Client::query()->count())->url(ClientResource::getUrl());
        }

        if ($user->can('requests.view')) {
            $new = StaffRequest::query()->where('status', RequestStatus::New)->count();
            $open = StaffRequest::query()->whereIn('status', [RequestStatus::InProgress, RequestStatus::Confirmed])->count();
            $stats[] = Stat::make(__('New requests'), $new)
                ->description(trans_choice('{0} None in progress|{1} :count in progress or confirmed|[2,*] :count in progress or confirmed', $open, ['count' => $open]))
                ->color($new > 0 ? 'warning' : 'gray')
                ->url(StaffRequestResource::getUrl());

            $active = [AssignmentStatus::Assigned, AssignmentStatus::Confirmed];
            $now = now();
            $stats[] = Stat::make(__('Active assignments'), Assignment::query()->whereIn('status', $active)
                ->whereHas('shift', fn ($q) => $q->where('starts_at', '<=', $now)->where('ends_at', '>=', $now))->count())
                ->description(__('On shift right now'))
                ->url(ShiftResource::getUrl());
            $stats[] = Stat::make(__('Upcoming assignments'), Assignment::query()->whereIn('status', $active)
                ->whereHas('shift', fn ($q) => $q->where('starts_at', '>', $now))->count())
                ->url(ShiftResource::getUrl());
            $stats[] = Stat::make(__('Completed assignments'), Assignment::query()
                ->where(fn ($q) => $q->where('status', AssignmentStatus::Completed)
                    ->orWhere(fn ($q) => $q->whereIn('status', $active)->whereHas('shift', fn ($s) => $s->where('ends_at', '<', $now))))
                ->count())
                ->description(__('All time'));
        }

        if ($user->can('timesheets.view')) {
            $pending = Timesheet::query()->where('status', TimesheetStatus::Submitted)->count();
            $stats[] = Stat::make(__('Timesheets to review'), $pending)
                ->color($pending > 0 ? 'warning' : 'gray')
                ->url(TimesheetResource::getUrl());
        }

        return $stats;
    }
}
