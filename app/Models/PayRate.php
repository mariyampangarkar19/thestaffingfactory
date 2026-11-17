<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hourly rate for a position; a client-specific row overrides the agency default.
 */
class PayRate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['job_role_id', 'client_id', 'hourly_rate', 'currency', 'effective_from'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2', 'effective_from' => 'date'];
    }

    /** @return BelongsTo<JobRole, $this> */
    public function jobRole(): BelongsTo
    {
        return $this->belongsTo(JobRole::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
