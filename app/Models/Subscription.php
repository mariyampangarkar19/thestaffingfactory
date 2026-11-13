<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agency's subscription. Read-only for the agency; changed by the platform side.
 */
class Subscription extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['plan_id', 'status', 'starts_on', 'ends_on', 'grace_days', 'cancelled_at', 'notes'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'grace_days' => 'integer', 'cancelled_at' => 'datetime'];
    }

    public function allowsAccess(): bool
    {
        return in_array($this->status, ['trial', 'active'], true)
            && now()->startOfDay()->lte($this->ends_on->copy()->addDays($this->grace_days));
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
