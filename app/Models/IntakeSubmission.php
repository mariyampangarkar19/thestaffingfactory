<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Receipt for a website form submission.
 */
class IntakeSubmission extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['integration_key_id', 'kind', 'external_ref', 'status', 'payload', 'error', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
