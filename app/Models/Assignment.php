<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A freelancer placed on a shift.
 */
class Assignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['shift_id', 'freelancer_profile_id', 'status', 'pay_rate', 'currency', 'notified_at'];

    protected function casts(): array
    {
        return ['pay_rate' => 'decimal:2', 'notified_at' => 'datetime'];
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /** @return BelongsTo<FreelancerProfile, $this> */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }

    /** @return HasOne<Timesheet, $this> */
    public function timesheet(): HasOne
    {
        return $this->hasOne(Timesheet::class);
    }
}
