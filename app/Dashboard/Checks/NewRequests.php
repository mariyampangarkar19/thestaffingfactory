<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Enums\RequestStatus;
use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use App\Models\StaffRequest;
use App\Models\User;

final class NewRequests implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('requests.manage')) {
            return null;
        }

        $count = StaffRequest::query()->where('status', RequestStatus::New)->count();

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} new staffing request|[2,*] new staffing requests', $count),
            __('Call the customer back, confirm the details and plan the shifts.'),
            __('Open'),
            StaffRequestResource::getUrl(parameters: ['filters' => ['status' => ['values' => [RequestStatus::New->value]]]]),
            'heroicon-o-inbox-arrow-down',
            'info',
        );
    }
}
