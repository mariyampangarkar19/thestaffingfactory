<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Add to every model whose table has a tenant_id column.
 * tests/Feature/Tenancy/RowLevelSecurityTest checks that the table also has RLS.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isPlatformMode()) {
                if (! $model->getAttribute('tenant_id')) {
                    throw new LogicException('Platform mode must set tenant_id explicitly.');
                }

                return;
            }

            $tenantId = $context->require()->getKey();

            if ($model->getAttribute('tenant_id') && $model->getAttribute('tenant_id') !== $tenantId) {
                throw new LogicException('Refusing to create a record for another agency.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('A record cannot be moved to another agency.');
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
