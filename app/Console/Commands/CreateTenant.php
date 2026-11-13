<?php

namespace App\Console\Commands;

use App\Actions\Platform\ProvisionTenant;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTenant extends Command
{
    protected $signature = 'platform:create-tenant {slug} {name} {owner_email} {--owner-name=Owner} {--country=CM} {--locale=fr} {--no-invite}';

    protected $description = 'Create an agency, its sub-domain and its first owner (who gets a set-password email).';

    public function handle(ProvisionTenant $provision): int
    {
        DB::setDefaultConnection('pgsql_platform');
        app(TenantContext::class)->enterPlatformMode();

        $tenant = $provision->handle([
            'slug' => (string) $this->argument('slug'),
            'name' => (string) $this->argument('name'),
            'owner_email' => (string) $this->argument('owner_email'),
            'owner_name' => (string) $this->option('owner-name'),
            'country_code' => (string) $this->option('country'),
            'default_locale' => (string) $this->option('locale'),
        ], sendInvite: ! $this->option('no-invite'));

        $this->info("Agency {$tenant->name} created at {$tenant->slug}.".config('platform.base_domain'));

        return self::SUCCESS;
    }
}
