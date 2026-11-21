<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Enums\TimesheetStatus;
use App\Filament\Crm\Resources\Timesheets\TimesheetResource;
use App\Models\Timesheet;
use App\Models\User;

final class TimesheetsToReview implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('timesheets.review')) {
            return null;
        }

        $count = Timesheet::query()->where('status', TimesheetStatus::Submitted)->count();

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} timesheet to approve|[2,*] timesheets to approve', $count),
            __('Freelancers are paid only for approved hours.'),
            __('Approve'),
            TimesheetResource::getUrl(),
            'heroicon-o-clock',
        );
    }
}
