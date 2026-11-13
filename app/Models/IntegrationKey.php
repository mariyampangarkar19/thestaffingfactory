<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $secret
 * @property list<string> $scopes
 * @property ?Carbon $revoked_at
 */
class IntegrationKey extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['name', 'secret', 'scopes', 'revoked_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'scopes' => 'array', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function allows(string $scope): bool
    {
        return $this->revoked_at === null && in_array($scope, $this->scopes ?? [], true);
    }
}
