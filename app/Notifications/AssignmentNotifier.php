<?php

namespace App\Notifications;

use App\Models\Assignment;
use App\Models\Shift;
use App\Models\Timesheet;
use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Filament\Notifications\Notification;

/**
 * In-portal notifications for freelancers (quote section 2 "Notifications").
 * Kept behind one class so SMS/WhatsApp/email channels can be added later without touching the actions.
 * Messages are written in the freelancer's language and the agency's time zone, not the operator's.
 */
class AssignmentNotifier
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function assigned(Assignment $assignment): void
    {
        $this->send($assignment, fn (array $when) => [
            __('New assignment'),
            __(':date, :from to :to. Open "My assignments" for the details.', $when),
        ]);
    }

    public function cancelled(Assignment $assignment): void
    {
        $this->send($assignment, fn (array $when) => [
            __('Assignment cancelled'),
            __('Your shift on :date has been cancelled.', $when),
        ]);
    }

    public function scheduleChanged(Assignment $assignment): void
    {
        $this->send($assignment, fn (array $when) => [
            __('Schedule change'),
            __('Your shift is now :date, :from to :to.', $when),
        ]);
    }

    public function timesheetReviewed(Timesheet $timesheet): void
    {
        $this->send($timesheet->assignment()->firstOrFail(), fn (array $when) => [
            __('Timesheet reviewed'),
            $when['date'].' · '.$timesheet->status->getLabel().($timesheet->review_note ? ' · '.$timesheet->review_note : ''),
        ]);
    }

    /** @param Closure(array{date: string, from: string, to: string}): array{string, string} $message */
    private function send(Assignment $assignment, Closure $message): void
    {
        $user = User::query()->whereHas('freelancerProfile', fn ($q) => $q->whereKey($assignment->freelancer_profile_id))->first();

        if ($user === null) {
            return; // No portal login yet.
        }

        $agency = $this->tenant->require();
        $previous = app()->getLocale();
        app()->setLocale($user->locale ?: $agency->default_locale ?: $previous);

        try {
            [$title, $body] = $message($this->when($assignment->shift()->firstOrFail(), $agency->timezone ?: 'UTC'));
        } finally {
            app()->setLocale($previous);
        }

        Notification::make()->title($title)->body($body)->sendToDatabase($user);
    }

    /** @return array{date: string, from: string, to: string} */
    private function when(Shift $shift, string $timezone): array
    {
        $start = $shift->starts_at->copy()->timezone($timezone)->locale(app()->getLocale());

        return [
            'date' => $start->isoFormat('ddd D MMM'),
            'from' => $start->format('H:i'),
            'to' => $shift->ends_at->copy()->timezone($timezone)->format('H:i'),
        ];
    }
}
