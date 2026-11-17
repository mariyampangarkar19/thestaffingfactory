<?php

namespace App\Actions\Scheduling;

use App\Audit\AuditLogger;
use App\Enums\AssignmentStatus;
use App\Enums\FreelancerStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Assignment;
use App\Models\FreelancerProfile;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\AssignmentNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Puts one freelancer on one shift, after checking they can actually work it.
 */
final class AssignFreelancerToShift
{
    public function __construct(
        private readonly ResolvePayRate $payRates,
        private readonly AssignmentNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Shift $shift, FreelancerProfile $freelancer, User $operator): Assignment
    {
        $assignment = DB::transaction(function () use ($shift, $freelancer, $operator): Assignment {
            // Lock the shift row so two operators can't overfill it at the same moment.
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);

            $this->guard($shift, $freelancer);

            $rate = $this->payRates->forShift($shift);

            $assignment = Assignment::query()->firstOrNew(['shift_id' => $shift->id, 'freelancer_profile_id' => $freelancer->id]);
            $assignment->forceFill([
                'status' => AssignmentStatus::Assigned,
                'pay_rate' => $rate['amount'],
                'currency' => $rate['currency'],
                'assigned_by' => $operator->id,
                'notified_at' => now(),
            ])->save();

            $this->audit->log('assignment.created', $assignment);

            return $assignment;
        });

        $this->notifier->assigned($assignment);

        return $assignment;
    }

    private function guard(Shift $shift, FreelancerProfile $freelancer): void
    {
        if (in_array($shift->status, [ShiftStatus::Completed, ShiftStatus::Cancelled], true)) {
            throw new DomainRuleViolation(__('This shift is closed.'));
        }

        if ($freelancer->status !== FreelancerStatus::Approved) {
            throw new DomainRuleViolation(__(':name is not approved yet.', ['name' => $freelancer->fullName()]));
        }

        $active = [AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed];

        $already = $shift->assignments()->where('freelancer_profile_id', $freelancer->id)->whereIn('status', $active)->exists();
        if ($already) {
            throw new DomainRuleViolation(__(':name is already on this shift.', ['name' => $freelancer->fullName()]));
        }

        if ($shift->assignments()->whereIn('status', $active)->count() >= $shift->headcount) {
            throw new DomainRuleViolation(__('This shift is already fully staffed.'));
        }

        $unavailable = $freelancer->unavailabilities()
            ->whereDate('starts_on', '<=', $shift->ends_at)
            ->whereDate('ends_on', '>=', $shift->starts_at)
            ->exists();
        if ($unavailable) {
            throw new DomainRuleViolation(__(':name is marked unavailable on that date.', ['name' => $freelancer->fullName()]));
        }

        $clash = Assignment::query()
            ->where('freelancer_profile_id', $freelancer->id)
            ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed])
            ->whereHas('shift', fn ($q) => $q->where('starts_at', '<', $shift->ends_at)->where('ends_at', '>', $shift->starts_at))
            ->exists();
        if ($clash) {
            throw new DomainRuleViolation(__(':name already has another shift at that time.', ['name' => $freelancer->fullName()]));
        }
    }
}
