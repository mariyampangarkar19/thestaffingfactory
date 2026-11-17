<?php

namespace App\Models;

use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer's request for staff. Arrives from the website form or is entered by an operator.
 * Status: new -> in_progress -> confirmed -> completed, or cancelled.
 */
class StaffRequest extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = ['client_id', 'client_contact_id', 'reference', 'title', 'details', 'location', 'starts_on', 'ends_on', 'status', 'channel', 'source', 'external_ref', 'assigned_to'];

    protected function casts(): array
    {
        return ['status' => RequestStatus::class, 'channel' => RequestChannel::class, 'starts_on' => 'date', 'ends_on' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<StaffRequestPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(StaffRequestPosition::class);
    }

    /** @return HasMany<Shift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /** @return MorphMany<Note, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** @return BelongsTo<ClientContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id');
    }
}
