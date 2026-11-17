<?php

namespace App\Actions\Freelancers;

use App\Audit\AuditLogger;
use App\Enums\FreelancerStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\FreelancerProfile;

/**
 * Screening, rejecting or deactivating. Approval has its own action because it creates the portal login.
 */
final class ChangeFreelancerStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FreelancerProfile $profile, FreelancerStatus $status): FreelancerProfile
    {
        if ($status === FreelancerStatus::Approved) {
            throw new DomainRuleViolation(__('Use the Approve action to approve a freelancer.'));
        }

        $from = $profile->status;
        $profile->forceFill(['status' => $status])->save();
        $this->audit->log('freelancer.status_changed', $profile, ['from' => $from->value, 'to' => $status->value]);

        return $profile;
    }
}
