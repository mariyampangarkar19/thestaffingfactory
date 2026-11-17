<?php

namespace Database\Seeders;

use App\Actions\Freelancers\ApproveFreelancer;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\MarkPaymentPaid;
use App\Actions\Platform\ProvisionTenant;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\CreateStaffRequest;
use App\Actions\Scheduling\AssignFreelancerToShift;
use App\Actions\Timesheets\ReviewTimesheet;
use App\Actions\Timesheets\SubmitTimesheet;
use App\Enums\FreelancerStatus;
use App\Enums\RequestChannel;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\ShiftStatus;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\JobRole;
use App\Models\QuizResult;
use App\Models\Shift;
use App\Models\StaffRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A demo agency with realistic sample data for testing and screenshots. Never runs in production.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Everyone signs in with the password in DEMO_PASSWORD (see .env.example).
 */
class DemoSeeder extends Seeder
{
    public const SLUG = 'tsf';

    public const STAFF = [
        'owner@demo.test' => ['Aline Mbarga', Role::Owner],
        'manager@demo.test' => ['Serge Ndongo', Role::Manager],
        'recruiter@demo.test' => ['Carine Fotso', Role::Recruiter],
        'sales@demo.test' => ['Yannick Essomba', Role::Sales],
        'finance@demo.test' => ['Mireille Tchoua', Role::Finance],
        'viewer@demo.test' => ['Patrick Abena', Role::Viewer],
    ];

    private CarbonImmutable $today;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The demo seeder must not run in production.');
        }

        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = $this->agency();

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            if (FreelancerProfile::query()->exists()) {
                $this->command?->warn('Demo data already present; nothing added. Run migrate:fresh first to start again.');

                return;
            }

            $this->today = CarbonImmutable::now($tenant->timezone ?: 'UTC')->startOfDay();
            DB::transaction(fn () => $this->seedAgency());
        });
    }

    private function agency(): Tenant
    {
        $existing = Tenant::on('pgsql_platform')->where('slug', self::SLUG)->first();

        if ($existing === null) {
            $previous = DB::getDefaultConnection();
            DB::setDefaultConnection('pgsql_platform');
            app(TenantContext::class)->enterPlatformMode();

            try {
                $existing = app(ProvisionTenant::class)->handle([
                    'slug' => self::SLUG,
                    'name' => 'The Staffing Factory',
                    'country_code' => 'CM',
                    'default_locale' => 'fr',
                    'owner_name' => self::STAFF['owner@demo.test'][0],
                    'owner_email' => 'owner@demo.test',
                ], sendInvite: false);
            } finally {
                DB::setDefaultConnection($previous);
                app(TenantContext::class)->clear();
            }
        }

        return Tenant::query()->findOrFail($existing->id);
    }

    private function seedAgency(): void
    {
        $password = (string) config('platform.demo_password');
        $staff = $this->staff($password);
        $roles = $this->positions();
        $freelancers = $this->freelancers($roles, $staff['recruiter'], $password);
        $clients = $this->clients($staff['sales']);

        $this->completedRequest($clients['hilton'], $roles, $freelancers, $staff);
        $this->upcomingRequest($clients['wedding'], $roles, $freelancers, $staff);
        $this->openRequests($clients, $roles, $staff);

        Announcement::query()->create([
            'title_en' => 'Bring your ID to every shift',
            'title_fr' => 'Apportez votre pièce d’identité à chaque mission',
            'body_en' => 'Customers check identity at the entrance. Arrive 15 minutes early, in the agreed dress code.',
            'body_fr' => 'Les clients vérifient l’identité à l’entrée. Arrivez 15 minutes en avance, dans la tenue convenue.',
            'published_at' => now()->subDay(),
        ]);
    }

    /** @return array<string, User> keyed by role name */
    private function staff(string $password): array
    {
        $staff = [];

        foreach (self::STAFF as $email => [$name, $role]) {
            $user = User::query()->where('email', $email)->first() ?? new User(['name' => $name, 'email' => $email]);
            $user->forceFill(['password' => $password, 'locale' => $role === Role::Owner ? 'fr' : null, 'email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);
            $staff[$role->value] = $user;
        }

        return $staff;
    }

    /** @return array<string, JobRole> keyed by slug */
    private function positions(): array
    {
        $rows = [
            'waiter' => ['Waiter', 'Serveur', 1500],
            'hostess' => ['Hostess', 'Hôtesse', 2000],
            'bartender' => ['Bartender', 'Barman', 2000],
            'cook' => ['Cook', 'Cuisinier', 2500],
            'cleaner' => ['Cleaner', 'Agent d’entretien', 1200],
            'security' => ['Security guard', 'Agent de sécurité', 1800],
        ];

        $roles = [];
        foreach ($rows as $slug => [$en, $fr, $rate]) {
            $roles[$slug] = JobRole::query()->create(['slug' => $slug, 'name_en' => $en, 'name_fr' => $fr]);
            $roles[$slug]->payRates()->create(['hourly_rate' => $rate, 'currency' => 'XAF', 'effective_from' => $this->today->subMonths(3)]);
        }

        return $roles;
    }

    /**
     * @param  array<string, JobRole>  $roles
     * @return list<FreelancerProfile> the approved ones, first is the demo portal login
     */
    private function freelancers(array $roles, User $recruiter, string $password): array
    {
        // name, city, positions, quiz score, status, source
        $people = [
            ['Brenda', 'Ngo Bassa', 'Douala', ['waiter', 'hostess'], 96, 'approved', 'facebook'],
            ['Junior', 'Kamga', 'Douala', ['waiter', 'bartender'], 91, 'approved', 'facebook'],
            ['Sandrine', 'Atangana', 'Yaoundé', ['hostess'], 88, 'approved', 'instagram'],
            ['Boris', 'Tchinda', 'Douala', ['waiter'], 93, 'approved', 'facebook'],
            ['Estelle', 'Manga', 'Yaoundé', ['waiter', 'cleaner'], 86, 'approved', null],
            ['Christian', 'Ewane', 'Douala', ['bartender'], 97, 'approved', 'facebook'],
            ['Larissa', 'Biloa', 'Douala', ['hostess', 'waiter'], 90, 'approved', 'tiktok'],
            ['Franck', 'Njoya', 'Bafoussam', ['cook'], 89, 'approved', null],
            ['Diane', 'Mballa', 'Yaoundé', ['hostess'], 92, 'new', 'facebook'],
            ['Arnaud', 'Fouda', 'Douala', ['security'], 85, 'new', 'facebook'],
            ['Nadège', 'Kouam', 'Douala', ['waiter'], 78, 'new', 'facebook'],
            ['Hervé', 'Ondoa', 'Yaoundé', ['cook', 'waiter'], 87, 'screening', 'instagram'],
            ['Pauline', 'Eyenga', 'Kribi', ['cleaner'], 62, 'rejected', 'facebook'],
            ['Thierry', 'Messi', 'Douala', ['waiter'], 94, 'inactive', null],
        ];

        $approved = [];

        foreach ($people as $i => [$first, $last, $city, $positions, $score, $status, $source]) {
            $email = $i === 0 ? 'freelancer@demo.test' : strtolower($first.'.'.str_replace(' ', '', $last)).'@demo.test';
            $signedUp = $this->today->subDays(40 - $i * 2)->addHours(10);

            $profile = new FreelancerProfile([
                'first_name' => $first, 'last_name' => $last, 'email' => $email,
                'phone' => '+237 6 '.(70 + $i).' 00 '.str_pad((string) ($i * 7 % 100), 2, '0', STR_PAD_LEFT).' '.(10 + $i),
                'city' => $city, 'languages' => $i % 3 === 0 ? ['fr', 'en'] : ['fr'],
                'years_experience' => 1 + $i % 6,
                'experience_summary' => 'Hôtels, mariages et événements d’entreprise.',
                'source' => $source, 'external_ref' => 'wp-'.(1000 + $i), 'consented_at' => $signedUp,
            ]);
            $profile->forceFill(['status' => in_array($status, ['approved', 'inactive'], true) ? 'new' : $status, 'created_at' => $signedUp])->save();
            $profile->jobRoles()->attach(collect($positions)->mapWithKeys(fn (string $slug) => [$roles[$slug]->id => ['tenant_id' => $profile->tenant_id]])->all());

            QuizResult::query()->create([
                'freelancer_profile_id' => $profile->id, 'quiz_role' => $positions[0], 'score' => $score,
                ...QuizResult::grade($score), 'locale' => 'fr', 'completed_at' => $signedUp->addMinutes(12),
            ]);

            if (in_array($status, ['approved', 'inactive'], true)) {
                app(ApproveFreelancer::class)->handle($profile, $recruiter);
                $profile->refresh()->user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();

                if ($status === 'inactive') {
                    $profile->forceFill(['status' => FreelancerStatus::Inactive])->save();
                } else {
                    $approved[] = $profile;
                }
            }
        }

        $approved[4]->unavailabilities()->create(['starts_on' => $this->today->addDays(2), 'ends_on' => $this->today->addDays(5), 'reason' => 'Voyage']);
        $approved[0]->evaluations()->create(['rating' => 5, 'comment' => 'Ponctuelle, très bon contact avec les invités.'])->forceFill(['created_by' => $recruiter->id])->save();

        return $approved;
    }

    /** @return array<string, Client> */
    private function clients(User $sales): array
    {
        $rows = [
            'hilton' => ['Hôtel Sawa Palace', 'Hotel', 'Douala', 'active', 'Mme Ebelle', 'Directrice banquets'],
            'wedding' => ['Élégance Événements', 'Events', 'Yaoundé', 'active', 'M. Owona', 'Gérant'],
            'resto' => ['Le Wouri Restaurant', 'Restaurant', 'Douala', 'active', 'Mme Dikoume', 'Propriétaire'],
            'corp' => ['Orion Télécom', 'Corporate', 'Douala', 'lead', 'M. Talla', 'Responsable achats'],
        ];

        $clients = [];
        foreach ($rows as $key => [$name, $sector, $city, $status, $contact, $title]) {
            $clients[$key] = Client::query()->create([
                'company_name' => $name, 'sector' => $sector, 'city' => $city, 'status' => $status,
                'email' => 'contact@'.$key.'.demo.test', 'phone' => '+237 2 33 42 '.(10 + count($clients)).' 00',
                'account_manager_id' => $sales->id,
            ]);
            $clients[$key]->contacts()->create(['name' => $contact, 'job_title' => $title, 'is_primary' => true, 'phone' => '+237 6 99 00 00 '.(10 + count($clients))]);
        }

        $note = $clients['hilton']->notes()->create(['kind' => 'call', 'body' => 'Confirme 4 serveurs et 2 hôtesses pour le gala. Tenue noire et blanche.', 'occurred_at' => now()->subDays(9)]);
        $note->forceFill(['created_by' => $sales->id])->save();

        return $clients;
    }

    /**
     * A finished event: hours submitted, most approved, one payment paid, one waiting.
     *
     * @param  array<string, JobRole>  $roles
     * @param  list<FreelancerProfile>  $f
     * @param  array<string, User>  $staff
     */
    private function completedRequest(Client $client, array $roles, array $f, array $staff): void
    {
        $day = $this->today->subDays(6);
        $request = $this->request($client, 'Gala annuel, 180 invités', $day, $day, RequestChannel::Phone, $staff, [
            ['job_role_id' => $roles['waiter']->id, 'quantity' => 3],
            ['job_role_id' => $roles['hostess']->id, 'quantity' => 2],
        ], 'Salle des fêtes, Bonanjo');

        $waiters = $this->shift($request, 0, $day->setTime(17, 0), $day->setTime(23, 0), 3, 30);
        $hostesses = $this->shift($request, 1, $day->setTime(18, 0), $day->setTime(22, 0), 2, 0);

        $assignments = [
            $this->assign($waiters, $f[0], $staff), $this->assign($waiters, $f[1], $staff), $this->assign($waiters, $f[3], $staff),
            $this->assign($hostesses, $f[2], $staff), $this->assign($hostesses, $f[6], $staff),
        ];

        app(ChangeRequestStatus::class)->handle($request, RequestStatus::InProgress);
        app(ChangeRequestStatus::class)->handle($request, RequestStatus::Confirmed);

        $review = app(ReviewTimesheet::class);
        $sheets = [];
        foreach ($assignments as $i => $assignment) {
            $shift = $assignment->shift;
            if ($i === 4) {
                continue; // Larissa has not submitted her hours yet.
            }
            $sheets[$i] = app(SubmitTimesheet::class)->handle($assignment, $shift->starts_at, $shift->ends_at->copy()->addMinutes($i === 1 ? 45 : 0), $shift->break_minutes, $i === 1 ? 'Resté pour le rangement.' : null);
        }

        $review->approve($sheets[0], $staff['manager']);
        $review->approve($sheets[1], $staff['manager'], 6 * 60, 'Rangement compté 30 minutes.');
        $review->approve($sheets[2], $staff['manager']);
        // $sheets[3] (Sandrine) stays "submitted" so there is something to review.

        $paid = app(CreatePayment::class)->handle($f[0], $staff['finance']);
        app(MarkPaymentPaid::class)->handle($paid, 'mobile_money', 'MP'.$day->format('ymd').'.1842.C47391', now()->subDays(2));
        app(CreatePayment::class)->handle($f[1], $staff['finance']);

        Shift::query()->whereKey([$waiters->id, $hostesses->id])->update(['status' => ShiftStatus::Completed]);
        app(ChangeRequestStatus::class)->handle($request->refresh(), RequestStatus::Completed);
    }

    /**
     * @param  array<string, JobRole>  $roles
     * @param  list<FreelancerProfile>  $f
     * @param  array<string, User>  $staff
     */
    private function upcomingRequest(Client $client, array $roles, array $f, array $staff): void
    {
        $day = $this->today->addDays(3);
        $request = $this->request($client, 'Mariage Owona, 250 invités', $day, $day->addDay(), RequestChannel::Website, $staff, [
            ['job_role_id' => $roles['waiter']->id, 'quantity' => 4],
            ['job_role_id' => $roles['bartender']->id, 'quantity' => 2],
            ['job_role_id' => $roles['hostess']->id, 'quantity' => 2, 'pay_rate' => 2500],
        ], 'Palais des Congrès, Yaoundé');

        $waiters = $this->shift($request, 0, $day->setTime(16, 0), $day->setTime(23, 30), 4, 30);
        $bar = $this->shift($request, 1, $day->setTime(17, 0), $day->addDay()->setTime(1, 0), 2, 30);
        $hostesses = $this->shift($request, 2, $day->setTime(15, 0), $day->setTime(20, 0), 2, 0);
        $this->shift($request, 0, $day->addDay()->setTime(10, 0), $day->addDay()->setTime(15, 0), 2, 0);

        foreach ([[$waiters, 0], [$waiters, 3], [$bar, 1], [$bar, 5], [$hostesses, 2], [$hostesses, 6]] as [$shift, $who]) {
            $this->assign($shift, $f[$who], $staff);
        }

        app(ChangeRequestStatus::class)->handle($request, RequestStatus::InProgress);
        app(ChangeRequestStatus::class)->handle($request, RequestStatus::Confirmed);

        // A lunch service yesterday, so the demo freelancer has hours waiting to be submitted.
        $yesterday = $this->today->subDay();
        $lunch = $this->request($client, 'Déjeuner d’entreprise', $yesterday, $yesterday, RequestChannel::Email, $staff, [
            ['job_role_id' => $roles['waiter']->id, 'quantity' => 2],
        ], 'Bastos, Yaoundé');
        $service = $this->shift($lunch, 0, $yesterday->setTime(11, 0), $yesterday->setTime(15, 0), 2, 0);
        $this->assign($service, $f[0], $staff);
        $this->assign($service, $f[3], $staff);
        app(ChangeRequestStatus::class)->handle($lunch, RequestStatus::Confirmed);
    }

    /**
     * @param  array<string, Client>  $clients
     * @param  array<string, JobRole>  $roles
     * @param  array<string, User>  $staff
     */
    private function openRequests(array $clients, array $roles, array $staff): void
    {
        $in = $this->today->addDays(10);

        $this->request($clients['resto'], 'Renfort week-end', $in, $in->addDays(2), RequestChannel::Website, $staff, [
            ['job_role_id' => $roles['waiter']->id, 'quantity' => 2],
            ['job_role_id' => $roles['cook']->id, 'quantity' => 1],
        ], 'Akwa, Douala', assign: false);

        $this->request($clients['corp'], 'Lancement produit, accueil', $in->addDays(8), $in->addDays(8), RequestChannel::Website, $staff, [
            ['job_role_id' => $roles['hostess']->id, 'quantity' => 6],
            ['job_role_id' => $roles['security']->id, 'quantity' => 2],
        ], 'Bonapriso, Douala', assign: false);

        $progress = $this->request($clients['resto'], 'Soirée privée', $in->addDays(4), $in->addDays(4), RequestChannel::WhatsApp, $staff, [
            ['job_role_id' => $roles['bartender']->id, 'quantity' => 1],
        ], 'Akwa, Douala');
        app(ChangeRequestStatus::class)->handle($progress, RequestStatus::InProgress);

        $cancelled = $this->request($clients['hilton'], 'Séminaire reporté', $in, $in, RequestChannel::Phone, $staff, [
            ['job_role_id' => $roles['cleaner']->id, 'quantity' => 2],
        ], 'Bonanjo, Douala');
        app(ChangeRequestStatus::class)->handle($cancelled, RequestStatus::Cancelled, 'Événement reporté par le client');
    }

    /**
     * @param  array<string, User>  $staff
     * @param  list<array<string, mixed>>  $positions
     */
    private function request(Client $client, string $title, CarbonImmutable $from, CarbonImmutable $to, RequestChannel $channel, array $staff, array $positions, string $location, bool $assign = true): StaffRequest
    {
        return app(CreateStaffRequest::class)->handle([
            'client_id' => $client->id,
            'client_contact_id' => $client->contacts()->value('id'),
            'title' => $title,
            'details' => 'Tenue noire et blanche. Service à table et accueil.',
            'location' => $location,
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'channel' => $channel,
            'assigned_to' => $assign ? $staff['sales']->id : null,
        ], $positions, $channel === RequestChannel::Website ? null : $staff['sales']->id, $channel === RequestChannel::Website ? 'integration' : 'user');
    }

    private function shift(StaffRequest $request, int $position, CarbonImmutable $from, CarbonImmutable $to, int $headcount, int $break): Shift
    {
        return Shift::query()->create([
            'staff_request_id' => $request->id,
            'staff_request_position_id' => $request->positions()->orderBy('created_at')->orderBy('id')->get()[$position]->id,
            'starts_at' => $from->utc(), 'ends_at' => $to->utc(),
            'headcount' => $headcount, 'break_minutes' => $break,
            'location' => $request->location, 'status' => ShiftStatus::Confirmed,
        ]);
    }

    /** @param array<string, User> $staff */
    private function assign(Shift $shift, FreelancerProfile $freelancer, array $staff): Assignment
    {
        return app(AssignFreelancerToShift::class)->handle($shift, $freelancer, $staff['recruiter'])->load('shift');
    }
}
