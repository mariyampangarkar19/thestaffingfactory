<?php

namespace App\Actions\Timesheets;

use App\Audit\AuditLogger;
use App\Enums\AssignmentStatus;
use App\Enums\TimesheetStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Assignment;
use App\Models\Timesheet;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * A freelancer reports the hours worked on one assignment. A rejected timesheet can be sent again.
 */
final class SubmitTimesheet
{
    private const MAX_SHIFT_MINUTES = 16 * 60;

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Assignment $assignment, CarbonInterface $startedAt, CarbonInterface $endedAt, int $breakMinutes = 0, ?string $note = null): Timesheet
    {
        if (! in_array($assignment->status, [AssignmentStatus::Assigned, AssignmentStatus::Confirmed, AssignmentStatus::Completed], true)) {
            throw new DomainRuleViolation(__('Hours cannot be submitted for a cancelled assignment.'));
        }

        $shift = $assignment->shift()->firstOrFail();

        if ($shift->starts_at->isFuture()) {
            throw new DomainRuleViolation(__('You can submit your hours once the shift has started.'));
        }

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw new DomainRuleViolation(__('The end time must be after the start time.'));
        }

        if ($endedAt->isFuture()) {
            throw new DomainRuleViolation(__('The end time cannot be in the future.'));
        }

        $minutes = (int) $startedAt->diffInMinutes($endedAt) - $breakMinutes;

        if ($breakMinutes < 0 || $minutes <= 0 || $minutes > self::MAX_SHIFT_MINUTES) {
            throw new DomainRuleViolation(__('Please check the times: the hours worked do not look right.'));
        }

        // Hours must sit around the planned shift, not on some other day.
        if ($startedAt->lt($shift->starts_at->copy()->subHours(6)) || $endedAt->gt($shift->ends_at->copy()->addHours(12))) {
            throw new DomainRuleViolation(__('These times are too far from the planned shift.'));
        }

        return DB::transaction(function () use ($assignment, $startedAt, $endedAt, $breakMinutes, $minutes, $note): Timesheet {
            $timesheet = Timesheet::query()->lockForUpdate()->firstOrNew(['assignment_id' => $assignment->id]);

            if ($timesheet->exists && $timesheet->status !== TimesheetStatus::Rejected) {
                throw new DomainRuleViolation(__('Hours were already submitted for this assignment.'));
            }

            $timesheet->forceFill([
                'freelancer_profile_id' => $assignment->freelancer_profile_id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'break_minutes' => $breakMinutes,
                'submitted_minutes' => $minutes,
                'approved_minutes' => null,
                'status' => TimesheetStatus::Submitted,
                'freelancer_note' => $note,
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ])->save();

            $this->audit->log('timesheet.submitted', $timesheet);

            return $timesheet;
        });
    }
}
