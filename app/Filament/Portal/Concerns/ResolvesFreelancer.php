<?php

namespace App\Filament\Portal\Concerns;

use App\Models\FreelancerProfile;

/**
 * Every portal screen starts from the signed-in person's own profile and never accepts a profile id from the browser.
 * That is what keeps one freelancer from seeing another's data, on top of the agency isolation in the database.
 */
trait ResolvesFreelancer
{
    protected static function freelancer(): FreelancerProfile
    {
        $profile = auth()->user()?->freelancerProfile()->first();

        abort_if($profile === null, 403);

        return $profile;
    }
}
