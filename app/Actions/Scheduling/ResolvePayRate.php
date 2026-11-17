<?php

namespace App\Actions\Scheduling;

use App\Models\PayRate;
use App\Models\Shift;

/**
 * Which hourly rate applies to a shift: the rate on the request position, else the customer's rate
 * for that position, else the agency default for that position.
 */
final class ResolvePayRate
{
    /** @return array{amount: ?string, currency: string} */
    public function forShift(Shift $shift): array
    {
        $position = $shift->position()->firstOrFail();

        if ($position->pay_rate !== null) {
            return ['amount' => $position->pay_rate, 'currency' => $position->currency];
        }

        $clientId = $shift->staffRequest()->value('client_id');

        $rate = PayRate::query()
            ->where('job_role_id', $position->job_role_id)
            ->whereDate('effective_from', '<=', $shift->starts_at)
            ->where(fn ($q) => $q->where('client_id', $clientId)->orWhereNull('client_id'))
            ->orderByRaw('client_id is null') // customer-specific first
            ->orderByDesc('effective_from')
            ->first();

        return ['amount' => $rate?->hourly_rate, 'currency' => $rate->currency ?? $position->currency];
    }
}
