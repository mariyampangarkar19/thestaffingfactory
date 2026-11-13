<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A position the agency staffs (waiter, hostess...).
 */
class JobRole extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['slug', 'name_en', 'name_fr', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function name(): string
    {
        return app()->getLocale() === 'fr' ? $this->name_fr : $this->name_en;
    }
}
