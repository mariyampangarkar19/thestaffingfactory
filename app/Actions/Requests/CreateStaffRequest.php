<?php

namespace App\Actions\Requests;

use App\Audit\AuditLogger;
use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Models\StaffRequest;
use App\Support\ReferenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * One way to create a staffing request, whether it comes from the website form or an operator.
 */
final class CreateStaffRequest
{
    public function __construct(private readonly ReferenceGenerator $references, private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Validated request fields.
     * @param  list<array{job_role_id: string, quantity: int, pay_rate?: float|string|null, notes?: ?string}>  $positions
     */
    public function handle(array $data, array $positions = [], ?string $createdBy = null, string $actorType = 'user'): StaffRequest
    {
        return DB::transaction(function () use ($data, $positions, $createdBy, $actorType): StaffRequest {
            $request = new StaffRequest($data);
            $request->forceFill([
                'reference' => $this->references->next('REQ', 'staff_requests'),
                'status' => RequestStatus::New,
                'channel' => $data['channel'] ?? RequestChannel::Manual,
                'created_by' => $createdBy,
            ])->save();

            foreach ($positions as $position) {
                $request->positions()->create($position);
            }

            $this->audit->log('request.created', $request, ['channel' => $request->channel->value], $actorType);

            return $request;
        });
    }
}
