<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Every job queued inside an agency carries that agency's id in its payload,
 * and the worker re-enters exactly that agency before running it (and leaves it afterwards).
 * Jobs queued outside an agency run with no agency, so they cannot see tenant data at all.
 */
final class QueueTenancy
{
    public static function register(): void
    {
        Queue::createPayloadUsing(function () {
            $context = app(TenantContext::class);

            return $context->has() ? ['tenant_id' => $context->id()] : [];
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $context = app(TenantContext::class);
            $context->clear();

            $tenantId = $event->job->payload()['tenant_id'] ?? null;
            if (is_string($tenantId)) {
                $context->set(Tenant::query()->findOrFail($tenantId));
            }
        });

        $leave = fn () => app(TenantContext::class)->clear();
        Event::listen(JobProcessed::class, $leave);
        Event::listen(JobExceptionOccurred::class, $leave);
    }
}
