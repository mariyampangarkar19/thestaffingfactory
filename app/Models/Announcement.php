<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Agency announcement shown to freelancers.
 */
class Announcement extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['title_en', 'title_fr', 'body_en', 'body_fr', 'published_at', 'expires_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
