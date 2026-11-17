<?php

namespace App\Reports;

use App\Filament\Crm\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Reports\Contracts\Report;
use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;

final class PaymentsReport implements Report
{
    public function title(): string
    {
        return __('Payments');
    }

    public function description(): string
    {
        return __('Payments created in the period, with status, method and transaction reference.');
    }

    public function permission(): string
    {
        return 'finance.view';
    }

    public function headings(): array
    {
        return [__('Reference'), __('Freelancer'), __('Period from'), __('Period to'), __('Amount'), __('Currency'), __('Status'), __('Method'), __('Transaction reference'), __('Paid on')];
    }

    public function rows(CarbonInterface $from, CarbonInterface $to): iterable
    {
        $tz = FilamentTimezone::get();

        foreach (Payment::query()->with('freelancerProfile')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->orderBy('id')->lazy(500) as $p) {
            yield [
                $p->reference,
                $p->freelancerProfile->fullName(),
                $p->period_start?->format('Y-m-d'),
                $p->period_end?->format('Y-m-d'),
                (float) $p->amount,
                $p->currency,
                $p->status->getLabel(),
                PaymentResource::methods()[$p->method] ?? $p->method,
                $p->transaction_ref,
                $p->paid_at?->timezone($tz)->format('Y-m-d'),
            ];
        }
    }
}
