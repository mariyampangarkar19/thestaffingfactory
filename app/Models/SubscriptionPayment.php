<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subscription payment recorded by the platform owner.
 */
class SubscriptionPayment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['subscription_id', 'amount', 'currency', 'paid_on', 'method', 'transaction_ref', 'covers_from', 'covers_to', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'covers_from' => 'date', 'covers_to' => 'date'];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
