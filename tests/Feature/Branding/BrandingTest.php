<?php

namespace Tests\Feature\Branding;

use App\Support\Branding;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The product name and logo come from config/branding.php only.
 */
class BrandingTest extends TestCase
{
    public function test_sign_in_page_shows_the_agency_and_the_product_name_from_the_branding_file(): void
    {
        $tenant = $this->createTenant();
        config(['branding.name' => 'Acme Staffing Cloud', 'branding.tagline' => 'Work together']);

        $this->get($this->tenantUrl($tenant, 'crm/login'))
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee('Powered by Acme Staffing Cloud')
            ->assertSee('Work together');
    }

    public function test_powered_by_line_can_be_switched_off(): void
    {
        $tenant = $this->createTenant();
        config(['branding.show_powered_by' => false]);

        $this->get($this->tenantUrl($tenant, 'crm/login'))->assertOk()->assertDontSee('Powered by');
    }

    public function test_logo_and_favicon_paths_become_public_urls_and_stay_empty_when_unset(): void
    {
        config(['branding.logo' => null, 'branding.favicon' => null]);
        $this->assertNull(Branding::logoUrl());
        $this->assertNull(Branding::faviconUrl());

        config(['branding.logo' => '/brand/logo.svg', 'branding.favicon' => 'brand/favicon.png']);
        $this->assertSame(asset('brand/logo.svg'), Branding::logoUrl());
        $this->assertSame(asset('brand/favicon.png'), Branding::faviconUrl());
    }

    public function test_laravel_and_email_sender_names_follow_the_branding_file(): void
    {
        $this->assertSame(config('branding.name'), config('app.name'));
        $this->assertSame(config('branding.name'), config('mail.from.name'));
    }

    public function test_the_product_name_is_written_nowhere_else_in_the_code(): void
    {
        $name = (string) config('branding.name');
        $offenders = [];

        foreach (['app', 'config', 'resources/views', 'resources/css', 'lang', 'database', 'routes'] as $folder) {
            foreach (File::allFiles(base_path($folder)) as $file) {
                if ($file->getRealPath() !== config_path('branding.php') && str_contains($file->getContents(), $name)) {
                    $offenders[] = $file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'Read the product name through App\Support\Branding instead of writing it out.');
    }
}
