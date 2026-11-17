<?php

namespace App\Actions\Requests;

use App\Audit\AuditLogger;
use App\Enums\AssignmentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Assignment;
use App\Models\StaffRequest;
use Illuminate\Support\Facades\DB;

final class ChangeRequestStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(StaffRequest $request, RequestStatus $to, ?string $reason = null): StaffRequest
    {
        $from = $request->status;

        if (! $from->canMoveTo($to)) {
            throw new DomainRuleViolation(__('A request that is :from cannot be changed to :to.', ['from' => $from->getLabel(), 'to' => $to->getLabel()]));
        }

        return DB::transaction(function () use ($request, $from, $to, $reason): StaffRequest {
            $request->forceFill([
                'status' => $to,
                'confirmed_at' => $to === RequestStatus::Confirmed ? now() : $request->confirmed_at,
                'cancelled_at' => $to === RequestStatus::Cancelled ? now() : null,
                'cancel_reason' => $to === RequestStatus::Cancelled ? $reason : null,
            ])->save();

            if ($to === RequestStatus::Cancelled) {
                // Cancelling a request frees the freelancers booked on its future shifts.
                $shiftIds = $request->shifts()->where('status', '!=', ShiftStatus::Completed)->pluck('id');
                $request->shifts()->whereIn('id', $shiftIds)->update(['status' => ShiftStatus::Cancelled]);
                Assignment::query()->whereIn('shift_id', $shiftIds)
                    ->whereIn('status', [AssignmentStatus::Assigned, AssignmentStatus::Confirmed])
                    ->update(['status' => AssignmentStatus::Cancelled]);
            }

            $this->audit->log('request.status_changed', $request, ['from' => $from->value, 'to' => $to->value]);

            return $request;
        });
    }
}
