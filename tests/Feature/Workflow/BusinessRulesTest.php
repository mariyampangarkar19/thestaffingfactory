<?php

namespace Tests\Feature\Workflow;

use App\Actions\Documents\StoreDocument;
use App\Actions\Freelancers\UpdateOwnProfile;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\MarkPaymentPaid;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Scheduling\AssignFreelancerToShift;
use App\Actions\Team\CreateStaffMember;
use App\Actions\Timesheets\ReviewTimesheet;
use App\Actions\Timesheets\SubmitTimesheet;
use App\Enums\AssignmentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\TimesheetStatus;
use App\Exceptions\DomainRuleViolation;
use App\Filament\Portal\Pages\MyAssignments;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\FreelancerProfile;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\StaffRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The rules behind the screens, checked against the demo agency: who can be assigned, how hours become money,
 * and who may open which file.
 */
class BusinessRulesTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(DemoSeeder::class);
        $this->tenant = Tenant::query()->where('slug', DemoSeeder::SLUG)->firstOrFail();
        app(TenantContext::class)->set($this->tenant);
    }

    private function staff(string $role): User
    {
        return User::query()->where('email', $role.'@demo.test')->firstOrFail();
    }

    private function freelancer(string $firstName): FreelancerProfile
    {
        return FreelancerProfile::query()->where('first_name', $firstName)->firstOrFail();
    }

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /** A real file on disk, so the type is read from its content exactly as in production. */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function refused(callable $action): void
    {
        try {
            $action();
        } catch (DomainRuleViolation) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The rule should have refused this.');
    }

    /** The under-staffed waiter shift on the wedding day (2 of 4). */
    private function openShift(): Shift
    {
        return Shift::query()->where('headcount', 4)->firstOrFail();
    }

    public function test_assigning_enforces_approval_position_clashes_availability_and_headcount(): void
    {
        $assign = app(AssignFreelancerToShift::class);
        $recruiter = $this->staff('recruiter');
        $shift = $this->openShift();

        $this->refused(fn () => $assign->handle($shift, $this->freelancer('Diane'), $recruiter));     // not approved yet
        $this->refused(fn () => $assign->handle($shift, $this->freelancer('Brenda'), $recruiter));    // already on this shift
        $this->refused(fn () => $assign->handle($shift, $this->freelancer('Estelle'), $recruiter));   // marked unavailable that day
        $this->refused(fn () => $assign->handle($shift, $this->freelancer('Christian'), $recruiter)); // on the bar shift at the same time

        $assignment = $assign->handle($shift, $this->freelancer('Franck'), $recruiter);
        $this->assertSame('1500.00', $assignment->pay_rate, 'The standard waiter rate is copied onto the assignment.');
        $this->assertSame(1, $assignment->freelancerProfile->user->notifications()->count());

        // Fill the last place, then one more is refused.
        $shift->update(['headcount' => 3]);
        $this->refused(fn () => $assign->handle($shift->refresh(), $this->freelancer('Thierry'), $recruiter));
    }

    public function test_hours_go_from_submitted_to_approved_to_paid_exactly_once(): void
    {
        $larissa = $this->freelancer('Larissa');
        $assignment = Assignment::query()->where('freelancer_profile_id', $larissa->id)
            ->whereHas('shift', fn ($q) => $q->where('ends_at', '<', now()))->with('shift')->firstOrFail();
        $shift = $assignment->shift;

        $submit = app(SubmitTimesheet::class);
        $this->refused(fn () => $submit->handle($assignment, $shift->ends_at, $shift->starts_at));                 // ends before it starts
        $this->refused(fn () => $submit->handle($assignment, $shift->starts_at, now()->addHour()));                // in the future
        $this->refused(fn () => $submit->handle($assignment, $shift->starts_at->copy()->subDays(2), $shift->ends_at)); // far from the shift

        $timesheet = $submit->handle($assignment, $shift->starts_at, $shift->ends_at->copy()->addMinutes(20), 0);
        $this->assertSame(260, $timesheet->submitted_minutes);
        $this->refused(fn () => $submit->handle($assignment, $shift->starts_at, $shift->ends_at));                 // already submitted

        $review = app(ReviewTimesheet::class);
        $manager = $this->staff('manager');
        $review->reject($timesheet, $manager, 'Merci de vérifier l’heure de fin.');
        $timesheet = $submit->handle($assignment, $shift->starts_at, $shift->ends_at, 0); // a rejected sheet can be sent again
        $review->approve($timesheet, $manager);
        $this->assertSame(TimesheetStatus::Approved, $timesheet->refresh()->status);

        // 4 h at the hostess rate of 2,000 XAF.
        $payment = app(CreatePayment::class)->handle($larissa, $this->staff('finance'));
        $this->assertSame('8000.00', $payment->amount);
        $this->assertSame('XAF', $payment->currency);
        $this->refused(fn () => app(CreatePayment::class)->handle($larissa, $this->staff('finance')));              // nothing left to pay
        $this->refused(fn () => $review->reject($timesheet, $manager, 'Too late'));                                 // on a payment: frozen

        app(MarkPaymentPaid::class)->handle($payment, 'mobile_money', 'TX-1');
        $this->refused(fn () => app(MarkPaymentPaid::class)->cancel($payment->refresh()));                          // paid stays paid
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    public function test_cancelling_a_pending_payment_makes_the_hours_payable_again(): void
    {
        $pending = Payment::query()->where('status', PaymentStatus::Pending)->firstOrFail();
        $freelancerId = $pending->freelancer_profile_id;
        $this->assertSame(0, CreatePayment::payable($freelancerId)->count());

        app(MarkPaymentPaid::class)->cancel($pending);

        $this->assertSame(1, CreatePayment::payable($freelancerId)->count());
    }

    public function test_money_is_rounded_to_the_nearest_unit_without_float_errors(): void
    {
        $this->assertSame('9000.00', CreatePayment::earnings(360, '1500.00'));
        $this->assertSame('10125.00', CreatePayment::earnings(405, '1500.00'));
        $this->assertSame('33.33', CreatePayment::earnings(20, '99.99'));
        $this->assertSame('0.03', CreatePayment::earnings(1, '1.99'));
    }

    public function test_request_status_follows_the_allowed_steps_and_cancelling_frees_the_freelancers(): void
    {
        $change = app(ChangeRequestStatus::class);

        $completed = StaffRequest::query()->where('status', RequestStatus::Completed)->firstOrFail();
        $this->refused(fn () => $change->handle($completed, RequestStatus::InProgress));

        $wedding = StaffRequest::query()->where('title', 'like', 'Mariage%')->firstOrFail();
        $booked = Assignment::query()->whereIn('shift_id', $wedding->shifts()->pluck('id'));
        $this->assertSame(6, (clone $booked)->where('status', AssignmentStatus::Assigned)->count());

        $change->handle($wedding, RequestStatus::Cancelled, 'Client a annulé');

        $this->assertSame(6, (clone $booked)->where('status', AssignmentStatus::Cancelled)->count());
        $this->assertSame('Client a annulé', $wedding->refresh()->cancel_reason);
    }

    public function test_an_agency_always_keeps_an_owner_and_nobody_changes_their_own_role(): void
    {
        $team = app(CreateStaffMember::class);
        $owner = $this->staff('owner');

        $this->refused(fn () => $team->changeRole($owner, Role::Viewer, $owner));
        $this->refused(fn () => $team->changeRole($owner, Role::Viewer, $this->staff('manager')));
        $this->refused(fn () => $team->handle('Dup', 'manager@demo.test', Role::Sales));

        $new = $team->handle('Nouvelle Recrue', 'New.Person@Demo.test', Role::Recruiter);
        $this->assertSame('new.person@demo.test', $new->email);
        $this->assertTrue($new->hasRole('recruiter'));

        $team->changeRole($this->staff('manager'), Role::Owner, $owner);
        $this->assertTrue($this->staff('manager')->hasRole('owner'));

        // Removing access signs the person out for good but keeps their record; they can be invited again.
        $this->refused(fn () => $team->removeAccess($owner, $owner));
        $sales = $this->staff('sales');
        $oldPassword = $sales->password;
        $team->removeAccess($sales, $owner);
        $sales->refresh();
        $this->assertFalse($sales->canAccessPanel(Filament::getPanel('crm')));
        $this->assertNotSame($oldPassword, $sales->password);
        $this->assertTrue($team->handle('Yannick Essomba', 'sales@demo.test', Role::Viewer)->hasRole('viewer'));
    }

    public function test_a_freelancer_only_changes_the_fields_they_own(): void
    {
        $brenda = $this->freelancer('Brenda');

        app(UpdateOwnProfile::class)->handle($brenda, [
            'city' => 'Limbé', 'status' => 'rejected', 'first_name' => 'Hacked', 'email' => 'other@demo.test', 'tenant_id' => 'x',
        ]);

        $brenda->refresh();
        $this->assertSame('Limbé', $brenda->city);
        $this->assertSame('Brenda', $brenda->first_name);
        $this->assertSame('approved', $brenda->status->value);
        $this->assertSame('freelancer@demo.test', $brenda->email);
    }

    public function test_portal_lists_only_the_signed_in_freelancers_own_assignments(): void
    {
        $brenda = $this->freelancer('Brenda');
        $own = MyAssignments::ownAssignments($brenda->id)->get();

        $this->assertGreaterThan(0, $own->count());
        $this->assertSame([$brenda->id], $own->pluck('freelancer_profile_id')->unique()->values()->all());
    }

    public function test_uploads_are_checked_by_content_and_stored_outside_the_public_folder(): void
    {
        $store = app(StoreDocument::class);
        $brenda = $this->freelancer('Brenda');

        // A script renamed to .pdf is refused because the content, not the name, decides.
        $fake = $this->upload('cv.pdf', "<?php echo 'x'; ?>");
        $this->refused(fn () => $store->handle($brenda, $fake, DocumentType::Cv));

        $tooBig = $this->upload('big.pdf', self::PDF.str_repeat('0', 11 * 1024 * 1024));
        $this->refused(fn () => $store->handle($brenda, $tooBig, DocumentType::Cv));

        $pdf = $this->upload('Mon CV.pdf', self::PDF);
        $document = $store->handle($brenda, $pdf, DocumentType::Cv, 'CV', true, $brenda->user_id);

        $this->assertStringStartsWith($this->tenant->id.'/', $document->path);
        $this->assertStringNotContainsString('Mon CV', $document->path, 'Stored under a random name.');
        Storage::disk('private')->assertExists($document->path);
        $this->assertSame(64, strlen($document->sha256));
    }

    public function test_a_file_is_only_downloaded_by_someone_allowed_to_see_it(): void
    {
        $brenda = $this->freelancer('Brenda');
        $junior = $this->freelancer('Junior');
        $pdf = fn () => $this->upload('doc.pdf', self::PDF);

        $own = app(StoreDocument::class)->handle($brenda, $pdf(), DocumentType::Cv, null, true, $brenda->user_id);
        $internal = app(StoreDocument::class)->handle($brenda, $pdf(), DocumentType::Contract, null, false, $this->staff('recruiter')->id);
        $slip = app(StoreDocument::class)->handle(Payment::query()->where('freelancer_profile_id', $brenda->id)->firstOrFail(), $pdf(), DocumentType::PaymentSlip, null, true, $this->staff('finance')->id);

        $get = function (?User $user, Document $document, ?Tenant $on = null) {
            Auth::forgetGuards();
            $this->flushSession(); // a new browser each time
            $session = $user ? [Auth::guard('web')->getName() => $user->id, 'password_hash_web' => $user->password] : [];

            return $this->withSession($session)->get($this->tenantUrl($on ?? $this->tenant, 'documents/'.$document->id.'/download'))->getStatusCode();
        };

        $brendaUser = $brenda->user()->firstOrFail();
        $juniorUser = $junior->user()->firstOrFail();
        $recruiter = $this->staff('recruiter');
        $finance = $this->staff('finance');
        $other = $this->createTenant('other');

        $this->assertSame(200, $get($brendaUser, $own));
        $this->assertSame(200, $get($brendaUser, $slip));
        $this->assertSame(403, $get($brendaUser, $internal), 'A file the agency did not share stays hidden from the freelancer.');
        $this->assertSame(403, $get($juniorUser, $own), 'Another freelancer cannot open it.');
        $this->assertSame(403, $get($juniorUser, $slip));
        $this->assertSame(200, $get($recruiter, $own));
        $this->assertSame(403, $get($recruiter, $slip), 'Payment slips need finance access.');
        $this->assertSame(200, $get($finance, $slip));
        $this->assertSame(403, $get(null, $own), 'Signed-out visitors get nothing.');
        $this->assertSame(403, $get($recruiter, $own, $other), 'The same link on another agency address does not work.');

        app(TenantContext::class)->set($this->tenant);
        $this->assertSame(4, AuditLog::query()->where('action', 'document.downloaded')->count(), 'Every download is logged.');
    }

    public function test_reports_export_to_excel_and_print_for_the_right_roles_only(): void
    {
        $get = function (string $email, string $report, string $format = 'xlsx', array $query = []) {
            Auth::forgetGuards();
            $user = app(TenantContext::class)->run($this->tenant, fn () => User::query()->where('email', $email)->firstOrFail());
            $params = http_build_query(['from' => now()->subDays(30)->toDateString(), 'to' => now()->addDays(30)->toDateString(), 'format' => $format, ...$query]);

            return $this->withSession([Auth::guard('web')->getName() => $user->id, 'password_hash_web' => $user->password])
                ->get($this->tenantUrl($this->tenant, "crm/reports/export/{$report}?{$params}"));
        };

        foreach (['assignments', 'hours', 'payments', 'freelancers'] as $report) {
            $response = $get('owner@demo.test', $report);
            $response->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', $response->streamedContent(), 'A real .xlsx (zip) file.');
        }

        $get('owner@demo.test', 'assignments', 'print')->assertOk()->assertSee('Hôtel Sawa Palace')->assertSee('Brenda Ngo Bassa');
        $get('manager@demo.test', 'payments')->assertOk();
        $get('sales@demo.test', 'assignments')->assertForbidden();
        $get('freelancer@demo.test', 'assignments')->assertForbidden();
        $get('owner@demo.test', 'unknown')->assertNotFound();
        $get('owner@demo.test', 'assignments', 'xlsx', ['from' => '2020-01-01'])->assertStatus(422);
        $get('owner@demo.test', 'assignments', 'csv')->assertStatus(302); // invalid format is a validation error
    }
}
