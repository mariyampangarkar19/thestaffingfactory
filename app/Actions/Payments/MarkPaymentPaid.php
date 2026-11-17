<?php

namespace App\Actions\Payments;

use App\Audit\AuditLogger;
use App\Enums\PaymentStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The platform records that the agency paid; it does not move money.
 */
final class MarkPaymentPaid
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Payment $payment, string $method, ?string $transactionRef = null, ?CarbonInterface $paidAt = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new DomainRuleViolation(__('Only a payment that is still to pay can be marked as paid.'));
        }

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'method' => $method,
            'transaction_ref' => $transactionRef,
            'paid_at' => $paidAt ?? now(),
        ])->save();

        $this->audit->log('payment.paid', $payment, ['method' => $method]);

        return $payment;
    }

    /** Cancelling releases the timesheets so they can go on a new payment. */
    public function cancel(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::Pending) {
            throw new DomainRuleViolation(__('A paid payment cannot be cancelled.'));
        }

        return DB::transaction(function () use ($payment): Payment {
            $payment->items()->delete();
            $payment->forceFill(['status' => PaymentStatus::Cancelled])->save();
            $this->audit->log('payment.cancelled', $payment);

            return $payment;
        });
    }
}
