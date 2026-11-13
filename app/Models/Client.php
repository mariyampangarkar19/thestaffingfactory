<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A hiring business (hotel, restaurant, event organiser). CRM record only, no login.
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['company_name', 'sector', 'email', 'phone', 'country_code', 'city', 'address', 'tax_number', 'status', 'source', 'external_ref', 'account_manager_id'];

    /** @return HasMany<StaffRequest, $this> */
    public function staffRequests(): HasMany
    {
        return $this->hasMany(StaffRequest::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }
}
