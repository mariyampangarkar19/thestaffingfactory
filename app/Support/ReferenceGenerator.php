<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Human-readable references per agency and year: REQ-2026-0001, PAY-2026-0001.
 * Must be called inside a transaction; an advisory lock stops two operators getting the same number.
 */
final class ReferenceGenerator
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function next(string $prefix, string $table): string
    {
        $tenantId = $this->tenant->require()->getKey();
        $stem = sprintf('%s-%s-', $prefix, now()->format('Y'));

        DB::select('select pg_advisory_xact_lock(hashtext(?))', [$tenantId.$stem]);

        $last = DB::table($table)->where('reference', 'like', $stem.'%')->max('reference');
        $number = $last ? ((int) substr((string) $last, strlen($stem))) + 1 : 1;

        return $stem.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
