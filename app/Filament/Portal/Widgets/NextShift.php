<?php

namespace App\Filament\Portal\Widgets;

use App\Enums\AssignmentStatus;
use App\Filament\Portal\Concerns\ResolvesFreelancer;
use App\Filament\Portal\Pages\MyAssignments;
use App\Filament\Portal\Pages\MyTimesheets;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Widgets\Widget;

/**
 * The top of the freelancer's home screen: hours still to submit, then the next shift in large print.
 */
class NextShift extends Widget
{
    use ResolvesFreelancer;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.portal.widgets.next-shift';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $profile = self::freelancer();
        $tz = FilamentTimezone::get();

        $next = MyAssignments::ownAssignments($profile->id)
            ->whereIn('assignments.status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed])
            ->where('shifts.ends_at', '>=', now())
            ->orderBy('shifts.starts_at')
            ->first();

        $shift = $next?->shift;

        return [
            'toSubmit' => MyTimesheets::awaitingHours($profile->id)->count(),
            'timesheetsUrl' => MyTimesheets::getUrl(),
            'assignmentsUrl' => MyAssignments::getUrl(),
            'next' => $shift === null ? null : [
                'when' => $shift->starts_at->timezone($tz)->diffForHumans(['parts' => 1]),
                'day' => $shift->starts_at->timezone($tz)->translatedFormat('l j F'),
                'hours' => $shift->starts_at->timezone($tz)->format('H:i').' – '.$shift->ends_at->timezone($tz)->format('H:i'),
                'position' => $shift->position->jobRole->name(),
                'event' => $shift->staffRequest->title,
                'client' => $shift->staffRequest->client->company_name,
                'place' => $shift->location ?: $shift->staffRequest->location,
                'notes' => $shift->staffRequest->details,
                'started' => $shift->starts_at->isPast(),
            ],
        ];
    }
}
