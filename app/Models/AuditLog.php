<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only. The database role cannot UPDATE or DELETE these rows; this guard makes the intent obvious in PHP too.
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }
}
