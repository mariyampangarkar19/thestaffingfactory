<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $domain
 * @property string $tenant_id
 */
class TenantDomain extends Model
{
    use HasUuids;

    protected $fillable = ['tenant_id', 'domain', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $d) => $d->domain = strtolower(trim($d->domain)));
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
