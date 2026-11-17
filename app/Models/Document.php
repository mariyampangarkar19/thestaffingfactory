<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uploaded file metadata. Files live on the private disk under {tenant_id}/..., never in /public.
 */
class Document extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = ['type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'status', 'expires_on', 'visible_to_freelancer'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['status' => DocumentStatus::class, 'type' => DocumentType::class, 'expires_on' => 'date', 'reviewed_at' => 'datetime', 'size' => 'integer', 'visible_to_freelancer' => 'boolean'];
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
