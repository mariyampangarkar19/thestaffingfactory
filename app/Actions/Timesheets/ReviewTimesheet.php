<?php

namespace App\Actions\Timesheets;

use App\Audit\AuditLogger;
use App\Enums\AssignmentStatus;
use App\Enums\TimesheetStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Timesheet;
use App\Models\User;
use App\Notifications\AssignmentNotifier;
use Illuminate\Support\Facades\DB;

/**
 * The agency approves the hours as submitted, approves a corrected number, or rejects them.
 */
final class ReviewTimesheet
{
    public function __construct(private readonly AssignmentNotifier $notifier, private readonly AuditLogger $audit) {}

    public function approve(Timesheet $timesheet, User $reviewer, ?int $adjustedMinutes = null, ?string $note = null): Timesheet
    {
        $adjusted = $adjustedMinutes !== null && $adjustedMinutes !== $timesheet->submitted_minutes;

        if ($adjusted && ($adjustedMinutes <= 0 || $adjustedMinutes > 16 * 60)) {
            throw new DomainRuleViolation(__('Please check the times: the hours worked do not look right.'));
        }

        if ($adjusted && blank($note)) {
            throw new DomainRuleViolation(__('Add a short reason when you change the hours.'));
        }

        return $this->decide($timesheet, $reviewer, $adjusted ? TimesheetStatus::Adjusted : TimesheetStatus::Approved, $adjusted ? $adjustedMinutes : $timesheet->submitted_minutes, $note);
    }

    public function reject(Timesheet $timesheet, User $reviewer, string $note): Timesheet
    {
        if (blank($note)) {
            throw new DomainRuleViolation(__('Add a short reason so the freelancer knows what to correct.'));
        }

        return $this->decide($timesheet, $reviewer, TimesheetStatus::Rejected, null, $note);
    }

    private function decide(Timesheet $timesheet, User $reviewer, TimesheetStatus $status, ?int $minutes, ?string $note): Timesheet
    {
        $timesheet = DB::transaction(function () use ($timesheet, $reviewer, $status, $minutes, $note): Timesheet {
            $timesheet = Timesheet::query()->lockForUpdate()->findOrFail($timesheet->id);

            if ($timesheet->paymentItem()->exists()) {
                throw new DomainRuleViolation(__('This timesheet is already part of a payment and cannot be changed.'));
            }

            $timesheet->forceFill([
                'status' => $status,
                'approved_minutes' => $minutes,
                'review_note' => $note,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            if ($status->isPayable()) {
                $timesheet->assignment()->update(['status' => AssignmentStatus::Completed]);
            }

            $this->audit->log('timesheet.'.$status->value, $timesheet, ['minutes' => $minutes]);

            return $timesheet;
        });

        $this->notifier->timesheetReviewed($timesheet);

        return $timesheet;
    }
}
