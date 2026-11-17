<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A freelancer placed on a shift.
 */
class Assignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['shift_id', 'freelancer_profile_id', 'status', 'pay_rate', 'currency', 'notified_at'];

    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class, 'pay_rate' => 'decimal:2', 'notified_at' => 'datetime'];
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

    /** @return MorphMany<Note, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
