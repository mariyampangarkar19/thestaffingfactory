<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run after every deploy. Fails if the web app's database role could bypass row-level security,
 * or if any table with a tenant_id column is missing RLS.
 */
class CheckDatabaseSecurity extends Command
{
    protected $signature = 'platform:check-db';

    protected $description = 'Verify tenant isolation settings in Postgres';

    public function handle(): int
    {
        $problems = [];
        $db = DB::connection('pgsql');

        $role = $db->selectOne('select rolname, rolsuper, rolbypassrls from pg_roles where rolname = current_user');
        if ($role->rolsuper || $role->rolbypassrls) {
            $problems[] = "App role {$role->rolname} is superuser or has BYPASSRLS.";
        }

        $owned = $db->select("select tablename from pg_tables where schemaname = 'public' and tableowner = current_user");
        if ($owned !== []) {
            $problems[] = 'App role owns tables: '.implode(', ', array_column($owned, 'tablename'));
        }

        foreach (self::unprotectedTenantTables() as $table) {
            $problems[] = "Table {$table} has tenant_id but row-level security is not enabled and forced.";
        }

        $canReadAdmins = $db->selectOne("select has_table_privilege(current_user, 'platform_admins', 'SELECT') as ok");
        if ($canReadAdmins->ok) {
            $problems[] = 'App role can read platform_admins.';
        }

        foreach ($problems as $p) {
            $this->error($p);
        }

        if ($problems === []) {
            $this->info('Tenant isolation checks passed.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    public static function unprotectedTenantTables(): array
    {
        $rows = DB::connection('pgsql')->select("
            select c.relname as table_name
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace and n.nspname = 'public'
            join pg_attribute a on a.attrelid = c.oid and a.attname = 'tenant_id' and not a.attisdropped
            where c.relkind = 'r'
              and c.relname not in ('tenant_domains')
              and not (c.relrowsecurity and c.relforcerowsecurity)
        ");

        return array_column($rows, 'table_name');
    }
}
