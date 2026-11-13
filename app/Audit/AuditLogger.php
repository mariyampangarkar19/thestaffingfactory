<?php

namespace App\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records who did what, inside the current agency. Store field names, not personal values.
 */
final class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /** @param array<string, mixed> $meta */
    public function log(string $action, ?Model $subject = null, array $meta = [], ?string $actorType = null, ?string $actorId = null): AuditLog
    {
        $user = $this->request->user();

        return AuditLog::query()->create([
            'actor_type' => $actorType ?? ($user ? 'user' : 'system'),
            'actor_id' => $actorId ?? $user?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255),
        ]);
    }
}
