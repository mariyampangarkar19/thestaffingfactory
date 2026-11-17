<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A work shift for one requested position.
 */
class Shift extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['staff_request_id', 'staff_request_position_id', 'starts_at', 'ends_at', 'break_minutes', 'location', 'headcount', 'status', 'notes'];

    protected function casts(): array
    {
        return ['status' => ShiftStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'break_minutes' => 'integer', 'headcount' => 'integer'];
    }

    /** @return BelongsTo<StaffRequest, $this> */
    public function staffRequest(): BelongsTo
    {
        return $this->belongsTo(StaffRequest::class);
    }

    /** @return BelongsTo<StaffRequestPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(StaffRequestPosition::class, 'staff_request_position_id');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
