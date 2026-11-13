<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    public function test_agency_login_page_loads_on_agency_host(): void
    {
        $tenant = $this->createTenant('tsf');
        $this->get($this->tenantUrl($tenant, 'crm/login'))->assertOk();
        $this->get($this->tenantUrl($tenant, 'portal/login'))->assertOk();
    }

    public function test_unknown_and_suspended_agencies_are_not_found(): void
    {
        $this->get('http://nobody.staffing.test/crm/login')->assertNotFound();

        $suspended = $this->createTenant('gone', status: 'suspended');
        $this->get($this->tenantUrl($suspended, 'crm/login'))->assertNotFound();
    }

    public function test_platform_panel_only_exists_on_admin_host(): void
    {
        $tenant = $this->createTenant('tsf');
        $this->get($this->tenantUrl($tenant, 'platform/login'))->assertNotFound();
        $this->get('http://admin.staffing.test/platform/login')->assertOk();
    }

    public function test_freelancer_cannot_open_the_crm(): void
    {
        $tenant = $this->createTenant('tsf');
        $freelancer = $this->createUser($tenant, 'freelancer');

        $this->actingAs($freelancer)->get($this->tenantUrl($tenant, 'crm'))->assertForbidden();
    }

    public function test_staff_session_does_not_work_on_another_agency(): void
    {
        $a = $this->createTenant('alpha');
        $b = $this->createTenant('beta');
        $owner = $this->createUser($a, 'owner');

        // A real session carrying alpha's user id, replayed on beta's host: the user can't be loaded there,
        // so the request is treated as a guest and sent to the login page.
        $this->withSession([Auth::guard('web')->getName() => $owner->id])
            ->get($this->tenantUrl($b, 'crm'))
            ->assertRedirect($this->tenantUrl($b, 'crm/login'));

        // Even if the user object were somehow present, their roles belong to alpha, so beta's CRM refuses them.
        $this->actingAs($owner)->get($this->tenantUrl($b, 'crm'))->assertForbidden();
    }

    public function test_staff_on_their_own_agency_must_set_up_two_factor_first(): void
    {
        $a = $this->createTenant('alpha');
        $owner = $this->createUser($a, 'owner');

        $this->withSession([Auth::guard('web')->getName() => $owner->id])
            ->get($this->tenantUrl($a, 'crm'))
            ->assertRedirectContains('multi-factor');
    }

    public function test_security_headers_are_sent(): void
    {
        $tenant = $this->createTenant('tsf');
        $this->get($this->tenantUrl($tenant, 'crm/login'))
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
