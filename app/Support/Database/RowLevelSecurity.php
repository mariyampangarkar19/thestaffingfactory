<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Helpers for migrations. Every new tenant-owned table must call protect().
 */
final class RowLevelSecurity
{
    public const CURRENT_TENANT = "NULLIF(current_setting('app.tenant_id', true), '')::uuid";

    /**
     * Turn on and force RLS so even the table owner is filtered, and allow rows
     * only for the agency in app.tenant_id. No agency set means no rows.
     */
    public static function protect(string $table): void
    {
        $t = self::ident($table);
        $current = self::CURRENT_TENANT;

        DB::statement("ALTER TABLE {$t} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$t} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$t}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$t} USING (tenant_id = {$current}) WITH CHECK (tenant_id = {$current})"
        );
        DB::statement("ALTER TABLE {$t} ALTER COLUMN tenant_id SET DEFAULT {$current}");
    }

    /**
     * protect() + the usual grants, for an ordinary agency-owned table.
     */
    public static function tenantTable(string $table, string $appPrivileges = 'SELECT, INSERT, UPDATE, DELETE'): void
    {
        self::protect($table);
        self::grantToApp($table, $appPrivileges);
        self::grantToPlatform($table);
    }

    /**
     * Only allow the listed values in a status-like column (enforced by the database, not just the app).
     *
     * @param  list<string>  $values
     */
    public static function allowOnly(string $table, string $column, array $values): void
    {
        $list = implode(', ', array_map(fn (string $v) => "'".str_replace("'", "''", $v)."'", $values));
        DB::statement('ALTER TABLE '.self::ident($table).' ADD CONSTRAINT '.self::ident("{$table}_{$column}_check")
            .' CHECK ('.self::ident($column)." IN ({$list}))");
    }

    public static function grantToApp(string $table, string $privileges = 'SELECT, INSERT, UPDATE, DELETE'): void
    {
        self::grant($table, $privileges, (string) config('platform.db_roles.app'));
    }

    public static function grantToPlatform(string $table, string $privileges = 'SELECT, INSERT, UPDATE, DELETE'): void
    {
        self::grant($table, $privileges, (string) config('platform.db_roles.platform'));
    }

    private static function grant(string $table, string $privileges, string $role): void
    {
        if (! self::roleExists($role)) {
            return; // Lets a developer run with a single role locally; CI and production always have both.
        }

        DB::statement('GRANT '.$privileges.' ON '.self::ident($table).' TO '.self::ident($role));
    }

    public static function grantSequenceUsage(): void
    {
        foreach (array_filter([config('platform.db_roles.app'), config('platform.db_roles.platform')]) as $role) {
            if (self::roleExists((string) $role)) {
                DB::statement('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO '.self::ident((string) $role));
            }
        }
    }

    private static function roleExists(string $role): bool
    {
        return $role !== '' && DB::selectOne('select 1 as ok from pg_roles where rolname = ?', [$role]) !== null;
    }

    private static function ident(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
