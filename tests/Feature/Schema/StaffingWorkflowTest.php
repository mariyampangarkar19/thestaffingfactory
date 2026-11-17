<?php

namespace Tests\Feature\Schema;

use App\Models\Assignment;
use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\JobRole;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Shift;
use App\Models\StaffRequest;
use App\Models\Subscription;
use App\Models\Timesheet;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Walks the main journey through the schema: customer -> request -> position -> shift -> assignment -> timesheet -> payment.
 */
class StaffingWorkflowTest extends TestCase
{
    public function test_full_staffing_journey_fits_the_schema(): void
    {
        $tenant = $this->createTenant('tsf');

        app(TenantContext::class)->run($tenant, function () {
            $waiter = JobRole::query()->create(['slug' => 'waiter', 'name_en' => 'Waiter', 'name_fr' => 'Serveur']);
            $client = Client::query()->create(['company_name' => 'Hôtel Makombe']);
            $client->contacts()->create(['name' => 'Mme Ngo', 'is_primary' => true]);

            $request = StaffRequest::query()->create([
                'client_id' => $client->id, 'reference' => 'REQ-2026-0001', 'title' => 'Gala dinner', 'channel' => 'website',
            ]);
            $request->notes()->create(['kind' => 'call', 'body' => 'Confirmed headcount by phone.']);
            $position = $request->positions()->create(['job_role_id' => $waiter->id, 'quantity' => 4, 'pay_rate' => 1500]);

            $shift = Shift::query()->create([
                'staff_request_id' => $request->id, 'staff_request_position_id' => $position->id,
                'starts_at' => '2026-12-05 17:00', 'ends_at' => '2026-12-05 23:00', 'headcount' => 4,
            ]);

            $freelancer = FreelancerProfile::query()->create(['first_name' => 'Awa', 'last_name' => 'M', 'status' => 'approved']);
            $freelancer->jobRoles()->attach($waiter->id, ['years_experience' => 2]);

            $assignment = Assignment::query()->create(['shift_id' => $shift->id, 'freelancer_profile_id' => $freelancer->id, 'pay_rate' => 1500]);

            $timesheet = Timesheet::query()->create([
                'assignment_id' => $assignment->id, 'freelancer_profile_id' => $freelancer->id,
                'started_at' => '2026-12-05 17:00', 'ended_at' => '2026-12-05 23:00', 'submitted_minutes' => 360,
            ]);

            $payment = Payment::query()->create(['freelancer_profile_id' => $freelancer->id, 'reference' => 'PAY-2026-0001', 'amount' => 9000]);
            $payment->items()->create(['timesheet_id' => $timesheet->id, 'minutes' => 360, 'hourly_rate' => 1500, 'amount' => 9000]);

            $this->assertSame(1, $request->shifts()->count());
            $this->assertSame('9000.00', $payment->fresh()->amount);
            $this->assertSame('Awa', $shift->assignments()->with('freelancerProfile')->sole()->freelancerProfile->first_name);
        });
    }

    public function test_database_rejects_unknown_statuses(): void
    {
        $tenant = $this->createTenant('tsf');

        $this->expectException(QueryException::class);
        app(TenantContext::class)->run($tenant, fn () => DB::table('freelancer_profiles')->insert([
            'id' => (string) Str::uuid7(), 'first_name' => 'X', 'last_name' => 'Y', 'status' => 'vip',
        ]));
    }

    public function test_agency_can_read_but_not_change_its_subscription(): void
    {
        $tenant = $this->createTenant('tsf');
        $other = $this->createTenant('other');

        $plan = Plan::on('pgsql_platform')->create(['code' => 'starter', 'name' => 'Starter', 'price' => 49]);
        foreach ([$tenant, $other] as $t) {
            DB::connection('pgsql_platform')->table('subscriptions')->insert([
                'id' => (string) Str::uuid7(), 'tenant_id' => $t->id, 'plan_id' => $plan->id,
                'status' => 'active', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(),
            ]);
        }

        $ctx = app(TenantContext::class);
        $mine = $ctx->run($tenant, fn () => Subscription::query()->get());
        $this->assertCount(1, $mine);
        $this->assertTrue($mine->first()->allowsAccess());

        $this->expectException(QueryException::class);
        $ctx->run($tenant, fn () => DB::table('subscriptions')->update(['ends_on' => '2099-01-01']));
    }
}
