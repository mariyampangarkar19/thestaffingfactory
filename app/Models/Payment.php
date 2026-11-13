<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment to a freelancer, recorded by the agency.
 */
class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['freelancer_profile_id', 'reference', 'period_start', 'period_end', 'amount', 'currency', 'status', 'method', 'transaction_ref', 'paid_at', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<FreelancerProfile, $this> */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }

    /** @return HasMany<PaymentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }
}
