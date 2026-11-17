<?php

namespace App\Filament\Portal\Widgets;

use App\Actions\Payments\CreatePayment;
use App\Enums\AssignmentStatus;
use App\Enums\PaymentStatus;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Filament\Portal\Pages\MyAssignments;
use App\Filament\Portal\Pages\MyPayments;
use App\Filament\Portal\Pages\MyTimesheets;
use App\Models\Assignment;
use App\Models\Payment;
use App\Support\Duration;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class PortalStats extends StatsOverviewWidget
{
    use ResolvesFreelancer;

    protected static ?int $sort = 10;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $profile = self::freelancer();
        $active = [AssignmentStatus::Assigned, AssignmentStatus::Confirmed];

        $upcoming = Assignment::query()->where('freelancer_profile_id', $profile->id)->whereIn('status', $active)
            ->whereHas('shift', fn ($q) => $q->where('ends_at', '>=', now()))->count();

        $toSubmit = MyTimesheets::awaitingHours($profile->id)->count();

        $approvedMinutes = (int) CreatePayment::payable($profile->id)->sum('approved_minutes');

        $lastPaid = Payment::query()->where('freelancer_profile_id', $profile->id)
            ->where('status', PaymentStatus::Paid)->latest('paid_at')->first();

        return [
            Stat::make(__('Upcoming assignments'), $upcoming)->url(MyAssignments::getUrl()),
            Stat::make(__('Shifts waiting for your hours'), $toSubmit)
                ->color($toSubmit > 0 ? 'warning' : 'gray')
                ->description($toSubmit > 0 ? __('Submit your hours so you can be paid.') : null)
                ->url(MyTimesheets::getUrl()),
            Stat::make(__('Approved hours not yet paid'), Duration::format($approvedMinutes))->url(MyPayments::getUrl()),
            Stat::make(__('Last payment'), $lastPaid ? Number::currency((float) $lastPaid->amount, $lastPaid->currency, app()->getLocale()) : '—')
                ->description($lastPaid?->paid_at?->translatedFormat('j M Y'))
                ->url(MyPayments::getUrl()),
        ];
    }
}
