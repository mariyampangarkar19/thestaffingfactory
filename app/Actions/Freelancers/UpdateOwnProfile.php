<?php

namespace App\Actions\Freelancers;

use App\Audit\AuditLogger;
use App\Models\FreelancerProfile;
use Illuminate\Support\Arr;

/**
 * What a freelancer may change about themselves. Name, email, status, positions and quiz results stay with the agency,
 * so only the fields listed here are ever written, whatever the browser sends.
 */
final class UpdateOwnProfile
{
    public const FIELDS = ['phone', 'whatsapp', 'city', 'address', 'languages', 'experience_summary', 'years_experience', 'availability_status'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data validated form state */
    public function handle(FreelancerProfile $profile, array $data): FreelancerProfile
    {
        $profile->fill(Arr::only($data, self::FIELDS));

        if ($profile->isDirty()) {
            $changed = array_keys($profile->getDirty());
            $profile->save();
            $this->audit->log('freelancer.profile_updated_by_self', $profile, ['fields' => $changed]);
        }

        return $profile;
    }
}
