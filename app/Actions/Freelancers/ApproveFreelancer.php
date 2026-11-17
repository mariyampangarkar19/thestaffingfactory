<?php

namespace App\Actions\Freelancers;

use App\Audit\AuditLogger;
use App\Enums\FreelancerStatus;
use App\Enums\Role;
use App\Exceptions\DomainRuleViolation;
use App\Models\FreelancerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Approving a registration gives the freelancer access to their portal (quote: registration workflow, steps 3-4).
 */
final class ApproveFreelancer
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(FreelancerProfile $profile, User $approver): FreelancerProfile
    {
        if ($profile->status === FreelancerStatus::Approved) {
            return $profile;
        }

        if (blank($profile->email)) {
            throw new DomainRuleViolation(__('Add an email address first: the freelancer needs it to sign in to the portal.'));
        }

        $invite = DB::transaction(function () use ($profile, $approver): bool {
            $created = false;

            if ($profile->user_id === null) {
                $user = User::query()->where('email', Str::lower($profile->email))->first();

                if ($user !== null && ! $user->hasRole(Role::Freelancer->value)) {
                    throw new DomainRuleViolation(__('This email already belongs to a staff account.'));
                }

                if ($user === null) {
                    $user = new User(['name' => $profile->fullName(), 'email' => Str::lower($profile->email), 'phone' => $profile->phone]);
                    $user->forceFill(['password' => Str::password(40)])->save();
                    $user->assignRole(Role::Freelancer->value);
                    $created = true;
                }

                $profile->user()->associate($user);
            }

            $profile->forceFill([
                'status' => FreelancerStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ])->save();

            $this->audit->log('freelancer.approved', $profile);

            return $created;
        });

        if ($invite) {
            // They choose their own password through the emailed link; no password is ever sent.
            Password::broker()->sendResetLink(['email' => Str::lower($profile->email)]);
        }

        return $profile;
    }
}
