<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Enums\FreelancerStatus;
use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Models\FreelancerProfile;
use App\Models\User;

final class NewRegistrations implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('freelancers.manage')) {
            return null;
        }

        $count = FreelancerProfile::query()->where('status', FreelancerStatus::New)->count();

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} new freelancer registration|[2,*] new freelancer registrations', $count),
            __('Check the quiz grade and documents, then approve or decline.'),
            __('Review'),
            FreelancerProfileResource::getUrl(parameters: ['filters' => ['status' => ['value' => FreelancerStatus::New->value]]]),
            'heroicon-o-user-plus',
        );
    }
}
