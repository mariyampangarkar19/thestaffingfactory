<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved timesheet covered by a payment.
 */
class PaymentItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['payment_id', 'timesheet_id', 'minutes', 'hourly_rate', 'amount'];

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'hourly_rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Timesheet, $this> */
    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }
}
