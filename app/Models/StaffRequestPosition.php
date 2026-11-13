<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One requested position line, e.g. 4 waiters.
 */
class StaffRequestPosition extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['staff_request_id', 'job_role_id', 'quantity', 'pay_rate', 'currency', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'pay_rate' => 'decimal:2'];
    }

    /** @return BelongsTo<StaffRequest, $this> */
    public function staffRequest(): BelongsTo
    {
        return $this->belongsTo(StaffRequest::class);
    }

    /** @return BelongsTo<JobRole, $this> */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    /** @return HasMany<Shift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
