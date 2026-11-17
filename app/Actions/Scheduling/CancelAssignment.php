<?php

namespace App\Actions\Scheduling;

use App\Audit\AuditLogger;
use App\Enums\AssignmentStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Assignment;
use App\Notifications\AssignmentNotifier;

final class CancelAssignment
{
    public function __construct(private readonly AssignmentNotifier $notifier, private readonly AuditLogger $audit) {}

    public function handle(Assignment $assignment, AssignmentStatus $as = AssignmentStatus::Cancelled): Assignment
    {
        if (! in_array($as, [AssignmentStatus::Cancelled, AssignmentStatus::NoShow], true)) {
            throw new DomainRuleViolation(__('An assignment can only be cancelled or marked as a no-show here.'));
        }

        if ($assignment->timesheet()->exists()) {
            throw new DomainRuleViolation(__('This assignment already has a timesheet and cannot be removed.'));
        }

        $assignment->forceFill(['status' => $as])->save();
        $this->audit->log('assignment.'.$as->value, $assignment);

        if ($as === AssignmentStatus::Cancelled) {
            $this->notifier->cancelled($assignment);
        }

        return $assignment;
    }
}
