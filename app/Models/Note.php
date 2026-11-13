<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Internal note or communication record on a customer, freelancer, request or assignment.
 */
class Note extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = ['kind', 'body', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
