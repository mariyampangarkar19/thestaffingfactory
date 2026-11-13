<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agency that leases the platform. The Staffing Factory is the first one.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $country_code
 * @property string $default_locale
 * @property string $status
 * @property array<string, mixed> $settings
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['slug', 'name', 'country_code', 'default_locale', 'timezone', 'status', 'plan', 'settings'];

    protected $attributes = ['settings' => '{}'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** @return HasMany<TenantDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** @return list<string> */
    public function enabledCountryPacks(): array
    {
        return array_values($this->settings['country_packs'] ?? [$this->country_code]);
    }
}
