<?php

namespace App\Models;

use App\Enums\TimesheetStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hours worked on an assignment: submitted by the freelancer, approved or adjusted by the agency.
 */
class Timesheet extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['assignment_id', 'freelancer_profile_id', 'started_at', 'ended_at', 'break_minutes', 'submitted_minutes', 'freelancer_note'];

    protected function casts(): array
    {
        return ['status' => TimesheetStatus::class, 'started_at' => 'datetime', 'ended_at' => 'datetime', 'break_minutes' => 'integer', 'submitted_minutes' => 'integer', 'approved_minutes' => 'integer', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<FreelancerProfile, $this> */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasOne<PaymentItem, $this> */
    public function paymentItem(): HasOne
    {
        return $this->hasOne(PaymentItem::class);
    }

    public function payableMinutes(): int
    {
        return (int) ($this->approved_minutes ?? 0);
    }
}
