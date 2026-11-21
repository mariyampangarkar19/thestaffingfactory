<?php

namespace Tests\Feature\Screens;

use App\Models\Announcement;
use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\JobRole;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\StaffRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Loads every CRM and portal screen against the demo agency, in both languages, and checks who is let in.
 */
class DemoScreensTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        $this->tenant = Tenant::query()->where('slug', DemoSeeder::SLUG)->firstOrFail();
    }

    private function signIn(string $email, string $locale = 'en'): static
    {
        $user = $this->inAgency(function () use ($email, $locale) {
            $user = User::query()->where('email', $email)->firstOrFail();
            // Staff must have two-step sign-in set up before the CRM opens.
            $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP', 'locale' => $locale])->save();

            return $user;
        });

        Auth::forgetGuards(); // each sign-in is a new browser session, not the previous test user

        return $this->withSession([Auth::guard('web')->getName() => $user->id, 'password_hash_web' => $user->password]);
    }

    private function inAgency(callable $callback): mixed
    {
        return app(TenantContext::class)->run($this->tenant, $callback);
    }

    /** @return list<string> */
    private function crmPaths(): array
    {
        return $this->inAgency(fn () => [
            'crm',
            'crm/freelancer-profiles',
            'crm/freelancer-profiles/create',
            'crm/freelancer-profiles/'.FreelancerProfile::query()->where('email', 'freelancer@demo.test')->value('id'),
            'crm/freelancer-profiles/'.FreelancerProfile::query()->value('id').'/edit',
            'crm/clients',
            'crm/clients/create',
            'crm/clients/'.Client::query()->value('id'),
            'crm/clients/'.Client::query()->value('id').'/edit',
            'crm/staff-requests',
            'crm/staff-requests/create',
            'crm/staff-requests/'.StaffRequest::query()->value('id'),
            'crm/staff-requests/'.StaffRequest::query()->value('id').'/edit',
            'crm/shifts',
            'crm/shifts/'.Shift::query()->value('id'),
            'crm/calendar',
            'crm/timesheets',
            'crm/payments',
            'crm/payments/'.Payment::query()->value('id'),
            'crm/job-roles',
            'crm/job-roles/create',
            'crm/job-roles/'.JobRole::query()->value('id').'/edit',
            'crm/team',
            'crm/announcements',
            'crm/announcements/create',
            'crm/announcements/'.Announcement::query()->value('id').'/edit',
            'crm/documents',
            'crm/reports',
        ]);
    }

    private const PORTAL_PATHS = ['portal', 'portal/assignments', 'portal/calendar', 'portal/timesheets', 'portal/payments', 'portal/documents', 'portal/profile'];

    #[DataProvider('locales')]
    public function test_owner_can_open_every_crm_screen(string $locale): void
    {
        foreach ($this->crmPaths() as $path) {
            $response = $this->signIn('owner@demo.test', $locale)->get($this->tenantUrl($this->tenant, $path));
            $this->assertSame(200, $response->status(), "{$path} [{$locale}] returned {$response->status()}: ".substr(strip_tags((string) $response->exception?->getMessage()), 0, 600));
        }
    }

    #[DataProvider('locales')]
    public function test_freelancer_can_open_every_portal_screen(string $locale): void
    {
        foreach (self::PORTAL_PATHS as $path) {
            $response = $this->signIn('freelancer@demo.test', $locale)->get($this->tenantUrl($this->tenant, $path));
            $this->assertSame(200, $response->status(), "{$path} [{$locale}] returned {$response->status()}: ".substr(strip_tags((string) $response->exception?->getMessage()), 0, 600));
        }
    }

    /** @return array<string, array{string}> */
    public static function locales(): array
    {
        return ['English' => ['en'], 'French' => ['fr']];
    }

    public function test_freelancer_is_refused_on_every_crm_screen_and_staff_on_the_portal(): void
    {
        foreach ($this->crmPaths() as $path) {
            $this->signIn('freelancer@demo.test')->get($this->tenantUrl($this->tenant, $path))->assertForbidden();
        }

        $this->signIn('owner@demo.test')->get($this->tenantUrl($this->tenant, 'portal'))->assertForbidden();
    }

    public function test_roles_only_reach_the_modules_they_are_given(): void
    {
        $open = fn (string $email, string $path) => $this->signIn($email)->get($this->tenantUrl($this->tenant, $path))->status();

        // Sales: customers and requests, not money, team or reports.
        $this->assertSame(200, $open('sales@demo.test', 'crm/clients'));
        $this->assertSame(403, $open('sales@demo.test', 'crm/payments'));
        $this->assertSame(403, $open('sales@demo.test', 'crm/team'));
        $this->assertSame(403, $open('sales@demo.test', 'crm/reports'));

        // Finance: payments and reports, not freelancer records or team.
        $this->assertSame(200, $open('finance@demo.test', 'crm/payments'));
        $this->assertSame(200, $open('finance@demo.test', 'crm/reports'));
        $this->assertSame(403, $open('finance@demo.test', 'crm/freelancer-profiles'));
        $this->assertSame(403, $open('finance@demo.test', 'crm/team'));

        // Read only: can look at freelancers, cannot open the create form.
        $this->assertSame(200, $open('viewer@demo.test', 'crm/freelancer-profiles'));
        $this->assertSame(403, $open('viewer@demo.test', 'crm/freelancer-profiles/create'));

        // Only the owner manages the team.
        $this->assertSame(403, $open('manager@demo.test', 'crm/team'));
    }

    public function test_owner_dashboard_says_what_needs_doing_today(): void
    {
        $this->signIn('owner@demo.test')->get($this->url('crm'))->assertOk()
            ->assertSee('Needs your attention')
            ->assertSee('upcoming shifts still need people')
            ->assertSee('new staffing requests')
            ->assertSee('new freelancer registrations')
            ->assertSee('payments waiting to be sent')
            ->assertSee('New staffing request');
    }

    public function test_read_only_user_gets_no_jobs_and_no_shortcuts(): void
    {
        $this->signIn('viewer@demo.test')->get($this->url('crm'))->assertOk()
            ->assertSee('All caught up')
            ->assertDontSee('New staffing request')
            ->assertDontSee('payments waiting to be sent');
    }

    public function test_freelancer_home_shows_the_next_shift_and_the_hours_to_submit(): void
    {
        $this->signIn('freelancer@demo.test')->get($this->url('portal'))->assertOk()
            ->assertSee('Your next shift')
            ->assertSee('Palais des Congrès, Yaoundé')
            ->assertSee('is waiting for your hours');
    }

    public function test_welcome_tour_shows_until_it_is_finished_and_again_on_request(): void
    {
        $this->inAgency(fn () => User::query()->where('email', 'owner@demo.test')->update(['tour_completed_at' => null]));

        $this->signIn('owner@demo.test')->get($this->url('crm'))->assertOk()->assertSee('sp-tour', false);
        $this->post($this->url('crm/tour/done'))->assertNoContent();

        $this->assertNotNull($this->inAgency(fn () => User::query()->where('email', 'owner@demo.test')->value('tour_completed_at')));
        $this->get($this->url('crm'))->assertOk()->assertDontSee('sp-tour__next', false);
        $this->get($this->url('crm?tour=1'))->assertOk()->assertSee('sp-tour__next', false);
    }

    public function test_tour_is_not_shown_on_the_sign_in_page(): void
    {
        $this->get($this->url('crm/login'))->assertOk()->assertDontSee('sp-tour__next', false);
    }

    private function url(string $path): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }
}
