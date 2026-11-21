<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Enums\ShiftStatus;
use App\Filament\Crm\Resources\Shifts\ShiftResource;
use App\Models\Shift;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;

/** Upcoming shifts that still have empty places, with the nearest one named. */
final class UnderstaffedShifts implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('assignments.manage')) {
            return null;
        }

        $open = ShiftResource::withStaffing(Shift::query())
            ->where('status', '!=', ShiftStatus::Cancelled)
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Shift $shift) => $shift->staffed_count < $shift->headcount);
        $count = $open->count();
        $nearest = $open->first()?->starts_at->timezone(FilamentTimezone::get());

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} upcoming shift still needs people|[2,*] upcoming shifts still need people', $count),
            __('The nearest is on :date. Assign approved freelancers before the customer is let down.', ['date' => $nearest?->translatedFormat('l j F')]),
            __('Fill'),
            ShiftResource::getUrl(parameters: ['filters' => ['understaffed' => ['isActive' => true]]]),
            'heroicon-o-exclamation-triangle',
            'danger',
        );
    }
}
