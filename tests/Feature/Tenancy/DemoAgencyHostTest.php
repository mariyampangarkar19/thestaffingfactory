<?php

namespace Tests\Feature\Tenancy;

use Tests\TestCase;

/**
 * Demo hosting with a single address (GitHub Codespaces) may serve one named agency on any host, never in production.
 */
class DemoAgencyHostTest extends TestCase
{
    private const DEMO_HOST = 'http://fuzzy-space-8000.app.github.dev/crm/login';

    public function test_unknown_host_is_not_found_by_default(): void
    {
        $this->createTenant('tsf');

        $this->get(self::DEMO_HOST)->assertNotFound();
    }

    public function test_named_demo_agency_is_served_on_any_host(): void
    {
        $tenant = $this->createTenant('tsf');
        config(['platform.demo_agency' => 'tsf']);

        $this->get(self::DEMO_HOST)->assertOk()->assertSee($tenant->name);
    }

    public function test_demo_agency_setting_is_ignored_in_production(): void
    {
        $this->createTenant('tsf');
        config(['platform.demo_agency' => 'tsf']);
        $this->app['env'] = 'production';

        $this->get(self::DEMO_HOST)->assertNotFound();
    }

    public function test_suspended_demo_agency_is_not_served(): void
    {
        $this->createTenant('tsf', 'suspended');
        config(['platform.demo_agency' => 'tsf']);

        $this->get(self::DEMO_HOST)->assertNotFound();
    }
}
