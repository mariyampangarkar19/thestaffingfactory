<?php

namespace App\Actions\Payments;

use App\Audit\AuditLogger;
use App\Enums\PaymentStatus;
use App\Enums\TimesheetStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\FreelancerProfile;
use App\Models\Payment;
use App\Models\Timesheet;
use App\Models\User;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Groups a freelancer's approved, not-yet-paid timesheets into one payment.
 * Earnings = approved hours x the rate copied onto the assignment (quote: "based on approved hours and configured rates").
 */
final class CreatePayment
{
    public function __construct(private readonly ReferenceGenerator $references, private readonly AuditLogger $audit) {}

    /** @param list<string>|null $timesheetIds null = every payable timesheet */
    public function handle(FreelancerProfile $freelancer, User $operator, ?array $timesheetIds = null): Payment
    {
        return DB::transaction(function () use ($freelancer, $operator, $timesheetIds): Payment {
            $timesheets = self::payable($freelancer->id)
                ->when($timesheetIds !== null, fn ($q) => $q->whereIn('id', $timesheetIds))
                ->with('assignment')
                ->lockForUpdate()
                ->get();

            if ($timesheets->isEmpty()) {
                throw new DomainRuleViolation(__('There are no approved hours waiting to be paid for this freelancer.'));
            }

            $missingRate = $timesheets->first(fn (Timesheet $t) => $t->assignment->pay_rate === null);
            if ($missingRate !== null) {
                throw new DomainRuleViolation(__('One of the assignments has no pay rate. Set the rate before creating the payment.'));
            }

            if ($timesheets->pluck('assignment.currency')->unique()->count() > 1) {
                throw new DomainRuleViolation(__('These timesheets use different currencies. Create one payment per currency.'));
            }

            $payment = new Payment([
                'freelancer_profile_id' => $freelancer->id,
                'period_start' => $timesheets->min('started_at')->toDateString(),
                'period_end' => $timesheets->max('ended_at')->toDateString(),
                'currency' => $timesheets->first()->assignment->currency,
                'amount' => 0,
            ]);
            $payment->forceFill([
                'reference' => $this->references->next('PAY', 'payments'),
                'status' => PaymentStatus::Pending,
                'recorded_by' => $operator->id,
            ])->save();

            $totalCents = 0;
            foreach ($timesheets as $timesheet) {
                $amount = self::earnings($timesheet->payableMinutes(), (string) $timesheet->assignment->pay_rate);
                $payment->items()->create([
                    'timesheet_id' => $timesheet->id,
                    'minutes' => $timesheet->payableMinutes(),
                    'hourly_rate' => $timesheet->assignment->pay_rate,
                    'amount' => $amount,
                ]);
                $totalCents += self::toCents($amount);
            }

            $payment->forceFill(['amount' => self::fromCents($totalCents)])->save();
            $this->audit->log('payment.created', $payment, ['timesheets' => $timesheets->count()]);

            return $payment;
        });
    }

    /** Approved timesheets that are not on any payment yet. */
    public static function payable(?string $freelancerId = null): Builder
    {
        return Timesheet::query()
            ->whereIn('status', [TimesheetStatus::Approved, TimesheetStatus::Adjusted])
            ->whereDoesntHave('paymentItem')
            ->when($freelancerId, fn ($q) => $q->where('freelancer_profile_id', $freelancerId));
    }

    /**
     * Money is calculated in whole minor units (integers) so there are no floating-point rounding surprises.
     */
    public static function earnings(int $minutes, string $hourlyRate): string
    {
        return self::fromCents(intdiv($minutes * self::toCents($hourlyRate) + 30, 60)); // +30 rounds to nearest
    }

    private static function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
