<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan agencies can be put on.
 */
class Plan extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'price', 'currency', 'billing_period', 'limits', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'limits' => 'array', 'is_active' => 'boolean'];
    }
}
