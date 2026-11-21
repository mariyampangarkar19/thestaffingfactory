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
use App\Models\Client;
use App\Models\FreelancerProfile;
use App\Models\JobRole;
use App\Models\QuizResult;
use App\Models\Shift;
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
        $people = $this->freelancers($roles, $staff['recruiter'], $password);
        $clients = $this->clients($staff['sales']);

        // Past work, oldest first. Everything older than two weeks has been paid.
        foreach ($this->pastEvents() as $event) {
            if ($event['days_ago'] === 13) {
                $this->payEveryone($people, $staff, $this->today->subDays(11));
            }
            $this->event($event, $clients, $roles, $people, $staff);
        }

        // Two payments built from last week's hours and waiting to be sent.
        app(CreatePayment::class)->handle($people['Junior'], $staff['finance']);
        app(CreatePayment::class)->handle($people['Christian'], $staff['finance']);

        foreach ($this->comingEvents() as $event) {
            $this->event($event, $clients, $roles, $people, $staff);
        }

        $this->announcements();
    }

    /** @return array<string, User> keyed by role name */
    private function staff(string $password): array
    {
        $staff = [];

        foreach (self::STAFF as $email => [$name, $role]) {
            $user = User::query()->where('email', $email)->first() ?? new User(['name' => $name, 'email' => $email]);
            // No saved language: demo logins follow the browser, and the EN | FR switch changes it.
            $user->forceFill(['password' => $password, 'locale' => null, 'email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);
            $staff[$role->value] = $user;
        }

        return $staff;
    }

    /** @return array<string, JobRole> keyed by slug */
    private function positions(): array
    {
        // Hourly rates in XAF, in line with event and hotel work in Douala and Yaoundé.
        $rows = [
            'waiter' => ['Waiter / waitress', 'Serveur / serveuse', 1500],
            'hostess' => ['Event host / hostess', 'Hôte / hôtesse d’accueil', 2000],
            'bartender' => ['Bartender', 'Barman / barmaid', 2000],
            'cook' => ['Cook', 'Cuisinier', 2500],
            'kitchen' => ['Kitchen assistant', 'Commis de cuisine', 1300],
            'room' => ['Room attendant', 'Valet / femme de chambre', 1300],
            'reception' => ['Receptionist', 'Réceptionniste', 1800],
            'security' => ['Security guard', 'Agent de sécurité', 1800],
            'promoter' => ['Brand promoter', 'Animateur / animatrice commerciale', 2200],
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
     * @return array<string, FreelancerProfile> approved freelancers keyed by first name; Brenda is the portal demo login
     */
    private function freelancers(array $roles, User $recruiter, string $password): array
    {
        // first, last, city, positions, quiz, status, source, languages, years, summary
        $rows = [
            ['Brenda', 'Ngo Bassa', 'Douala', ['waiter', 'hostess'], 96, 'approved', 'facebook', ['fr', 'en'], 4, 'Service en salle à l’Hôtel Rives du Wouri pendant deux ans, puis banquets et mariages. Très à l’aise avec la clientèle internationale.'],
            ['Junior', 'Kamga', 'Douala', ['waiter', 'bartender'], 91, 'approved', 'facebook', ['fr'], 3, 'Barman en lounge à Bonapriso, service cocktail et vin. Disponible le soir et le week-end.'],
            ['Sandrine', 'Atangana', 'Yaoundé', ['hostess', 'reception'], 88, 'approved', 'instagram', ['fr', 'en'], 2, 'Hôtesse d’accueil pour salons et conférences au Palais des Congrès. BTS en communication.'],
            ['Boris', 'Tchinda', 'Douala', ['waiter', 'kitchen'], 93, 'approved', 'facebook', ['fr'], 5, 'Chef de rang en restaurant à Akwa. Connaît le service à l’assiette et le buffet.'],
            ['Estelle', 'Manga', 'Yaoundé', ['room', 'waiter'], 86, 'approved', null, ['fr'], 3, 'Femme de chambre en résidence hôtelière à Bastos. Rigoureuse et rapide.'],
            ['Christian', 'Ewane', 'Douala', ['bartender'], 97, 'approved', 'facebook', ['fr', 'en'], 6, 'Barman confirmé, formation flair. A tenu le bar de plusieurs galas d’entreprise.'],
            ['Larissa', 'Biloa', 'Douala', ['hostess', 'promoter'], 90, 'approved', 'tiktok', ['fr'], 2, 'Animatrice commerciale pour des marques de boissons en grande surface. Très bon contact.'],
            ['Franck', 'Njoya', 'Douala', ['cook', 'kitchen'], 89, 'approved', null, ['fr'], 7, 'Cuisinier, spécialités camerounaises et cuisine internationale. Ancien second de cuisine à Bafoussam.'],
            ['Grace', 'Tabi', 'Douala', ['reception', 'hostess'], 94, 'approved', 'facebook', ['en', 'fr'], 3, 'Bilingual receptionist from Buea, front desk experience in a Limbe beach hotel.'],
            ['Emmanuel', 'Fon', 'Yaoundé', ['security'], 87, 'approved', null, ['en', 'fr'], 8, 'Former security supervisor for a bank branch. Crowd control at concerts and fairs.'],
            ['Mireille', 'Ekotto', 'Yaoundé', ['waiter', 'hostess'], 92, 'approved', 'instagram', ['fr'], 2, 'Service traiteur pour mariages à Yaoundé. Disponible le week-end.'],
            ['Kevin', 'Ndi', 'Douala', ['waiter', 'security'], 86, 'approved', 'facebook', ['fr', 'en'], 1, 'Étudiant à l’Université de Douala, extra en restauration depuis un an.'],
            ['Aïcha', 'Bello', 'Yaoundé', ['promoter', 'hostess'], 95, 'approved', 'tiktok', ['fr', 'en'], 3, 'Animatrice pour lancements de produits télécom. Parle aussi fulfulde.'],
            ['Joël', 'Mbappe Eboa', 'Kribi', ['waiter', 'room'], 85, 'approved', 'facebook', ['fr'], 4, 'Saison en lodge à Kribi, service petit-déjeuner et chambres.'],
            ['Diane', 'Mballa', 'Yaoundé', ['hostess'], 92, 'new', 'facebook', ['fr'], 1, 'Hôtesse pour cérémonies de mariage, très bonne présentation.'],
            ['Arnaud', 'Fouda', 'Douala', ['security'], 85, 'new', 'facebook', ['fr'], 4, 'Agent de sécurité en centre commercial à Douala.'],
            ['Ruth', 'Nkeng', 'Douala', ['reception', 'hostess'], 90, 'new', 'instagram', ['en', 'fr'], 2, 'Front desk agent, bilingual, from Bamenda. Moved to Douala this year.'],
            ['Nadège', 'Kouam', 'Douala', ['waiter'], 78, 'new', 'facebook', ['fr'], 0, 'Première expérience recherchée dans la restauration.'],
            ['Hervé', 'Ondoa', 'Yaoundé', ['cook', 'kitchen'], 87, 'screening', 'instagram', ['fr'], 5, 'Cuisinier en restaurant de grillades à Mvog-Mbi.'],
            ['Pauline', 'Eyenga', 'Kribi', ['room'], 62, 'rejected', 'facebook', ['fr'], 0, 'Recherche un emploi de ménage.'],
            ['Thierry', 'Messi', 'Douala', ['waiter'], 94, 'inactive', null, ['fr'], 6, 'A rejoint un hôtel en contrat permanent.'],
        ];

        $approved = [];

        foreach ($rows as $i => [$first, $last, $city, $positions, $score, $status, $source, $languages, $years, $summary]) {
            $email = $i === 0 ? 'freelancer@demo.test' : $this->emailFor($first, $last);
            $signedUp = $this->today->subDays(55 - $i * 2)->addHours(9 + $i % 8);

            $profile = new FreelancerProfile([
                'first_name' => $first, 'last_name' => $last, 'email' => $email,
                'phone' => $this->mobile($i), 'whatsapp' => $this->mobile($i),
                'city' => $city, 'languages' => $languages, 'years_experience' => $years, 'experience_summary' => $summary,
                'source' => $source, 'external_ref' => 'wp-'.(2400 + $i), 'consented_at' => $signedUp,
            ]);
            $profile->forceFill(['status' => in_array($status, ['approved', 'inactive'], true) ? 'new' : $status, 'created_at' => $signedUp])->save();
            $profile->jobRoles()->attach(collect($positions)->mapWithKeys(fn (string $slug) => [$roles[$slug]->id => ['tenant_id' => $profile->tenant_id]])->all());

            QuizResult::query()->create([
                'freelancer_profile_id' => $profile->id, 'quiz_role' => $positions[0], 'score' => $score,
                ...QuizResult::grade($score), 'locale' => in_array('en', $languages, true) && $languages[0] === 'en' ? 'en' : 'fr',
                'completed_at' => $signedUp->addMinutes(11 + $i % 9),
            ]);

            if (in_array($status, ['approved', 'inactive'], true)) {
                app(ApproveFreelancer::class)->handle($profile, $recruiter);
                $profile->refresh()->user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();

                if ($status === 'inactive') {
                    $profile->forceFill(['status' => FreelancerStatus::Inactive])->save();
                } else {
                    $approved[$first] = $profile;
                }
            }
        }

        $approved['Estelle']->unavailabilities()->create(['starts_on' => $this->today->addDays(2), 'ends_on' => $this->today->addDays(5), 'reason' => 'Examens à l’université']);
        $approved['Kevin']->unavailabilities()->create(['starts_on' => $this->today->addDays(9), 'ends_on' => $this->today->addDays(12), 'reason' => 'Voyage à Bafoussam']);

        foreach ([
            ['Brenda', 5, 'Ponctuelle, très bon contact avec les invités. Le client a demandé à la revoir.'],
            ['Christian', 5, 'Bar tenu seul pour 120 invités, aucune rupture. À reprendre pour les galas.'],
            ['Boris', 4, 'Efficace au buffet. Penser à rappeler la tenue (chaussures noires).'],
            ['Grace', 5, 'Bilingual welcome desk for the Orion launch, excellent with foreign guests.'],
            ['Kevin', 3, 'Arrivé 20 minutes en retard au déjeuner Orion. À surveiller.'],
        ] as [$name, $rating, $comment]) {
            $approved[$name]->evaluations()->create(['rating' => $rating, 'comment' => $comment])->forceFill(['created_by' => $recruiter->id])->save();
        }

        return $approved;
    }

    /** @return array<string, Client> */
    private function clients(User $sales): array
    {
        // key => name, sector, city, district, status, contact, title, note
        $rows = [
            'rives' => ['Hôtel Rives du Wouri', 'Hotel', 'Douala', 'Bonanjo', 'active', 'Mme Esther Ebelle', 'Directrice banquets', 'Préfère les équipes déjà connues. Tenue noire et blanche, chaussures fermées.'],
            'manguiers' => ['Résidence Les Manguiers', 'Hotel', 'Yaoundé', 'Bastos', 'active', 'M. Roland Mvondo', 'Gouvernant général', 'Renforts réguliers en chambres le week-end. Paie à 30 jours.'],
            'elegance' => ['Élégance Événements', 'Events', 'Yaoundé', 'Omnisport', 'active', 'M. Paul Owona', 'Gérant', 'Organisateur de mariages, 2 à 3 événements par mois en saison sèche.'],
            'wouri' => ['Le Wouri Restaurant', 'Restaurant', 'Douala', 'Akwa', 'active', 'Mme Danielle Dikoume', 'Propriétaire', 'Besoin récurrent le vendredi et le samedi soir.'],
            'expo' => ['Cameroun Expo Services', 'Events', 'Douala', 'Bonapriso', 'active', 'M. Samuel Ngando', 'Chef de projet', 'Salons professionnels et foires. Demandes à la dernière minute.'],
            'orion' => ['Orion Télécom', 'Corporate', 'Douala', 'Bonapriso', 'active', 'M. Guy Talla', 'Responsable événements', 'Lancements de forfaits avec hôtesses bilingues et animateurs.'],
            'littoral' => ['Littoral Boissons SA', 'Corporate', 'Douala', 'Bassa', 'lead', 'Mme Carole Moukoko', 'Cheffe de marque', 'Intéressée par des animatrices pour une tournée en grandes surfaces. Devis envoyé.'],
            'kribi' => ['Kribi Sea Lodge', 'Hotel', 'Kribi', 'Mpalla', 'inactive', 'M. Jean Nlend', 'Directeur', 'Saison terminée. Recontacter en novembre pour les fêtes.'],
        ];

        $clients = [];
        $n = 0;
        foreach ($rows as $key => [$name, $sector, $city, $district, $status, $contact, $title, $note]) {
            $clients[$key] = Client::query()->create([
                'company_name' => $name, 'sector' => $sector, 'city' => $city, 'address' => $district.', '.$city, 'status' => $status,
                'email' => 'contact@'.$key.'.demo.test', 'phone' => '+237 2 33 4'.$n.' '.(12 + $n * 7).' '.(30 + $n),
                'source' => $n % 3 === 0 ? 'website' : 'referral',
                'account_manager_id' => $sales->id,
            ]);
            $clients[$key]->contacts()->create(['name' => $contact, 'job_title' => $title, 'is_primary' => true, 'phone' => $this->mobile(40 + $n)]);
            $clients[$key]->notes()->create(['kind' => 'note', 'body' => $note, 'occurred_at' => now()->subDays(30 - $n)])->forceFill(['created_by' => $sales->id])->save();
            $n++;
        }

        $clients['rives']->notes()->create(['kind' => 'call', 'body' => 'Confirme le gala de fin d’année : 4 serveurs, 2 barmen, 3 hôtesses. Devis accepté par téléphone.', 'occurred_at' => now()->subDays(4)])->forceFill(['created_by' => $sales->id])->save();
        $clients['orion']->notes()->create(['kind' => 'whatsapp', 'body' => 'Demande 6 hôtesses bilingues pour le lancement du forfait Jeunes. Briefing produit la veille à 17 h.', 'occurred_at' => now()->subDays(2)])->forceFill(['created_by' => $sales->id])->save();
        $clients['littoral']->notes()->create(['kind' => 'meeting', 'body' => 'Rendez-vous à Bassa : tournée de 4 samedis dans 6 supermarchés. Attend notre devis final.', 'occurred_at' => now()->subDays(6)])->forceFill(['created_by' => $sales->id])->save();

        return $clients;
    }

    /**
     * Finished jobs over the last seven weeks. 'missing' did not submit hours; 'pending' are submitted but not yet approved.
     *
     * @return list<array<string, mixed>>
     */
    private function pastEvents(): array
    {
        return [
            ['days_ago' => 46, 'client' => 'expo', 'title' => 'Salon de l’agro-industrie, stand accueil', 'place' => 'Hall des expositions, Bonapriso, Douala', 'channel' => RequestChannel::Email,
                'shifts' => [['hostess', 8, 17, 60, ['Larissa', 'Grace']], ['security', 7, 18, 60, ['Kevin']]]],
            ['days_ago' => 39, 'client' => 'elegance', 'title' => 'Mariage Fotso – Ngassa, 300 invités', 'place' => 'Salle Hilltop, Yaoundé', 'channel' => RequestChannel::Website,
                'shifts' => [['waiter', 15, 23, 30, ['Mireille', 'Estelle', 'Brenda']], ['bartender', 16, 24, 30, ['Christian']]]],
            ['days_ago' => 33, 'client' => 'manguiers', 'title' => 'Renfort chambres, week-end de l’Ascension', 'place' => 'Résidence Les Manguiers, Bastos', 'channel' => RequestChannel::Phone,
                'shifts' => [['room', 7, 15, 30, ['Estelle', 'Joël']]]],
            ['days_ago' => 26, 'client' => 'wouri', 'title' => 'Soirée jazz, service en salle', 'place' => 'Le Wouri Restaurant, Akwa', 'channel' => RequestChannel::WhatsApp,
                'shifts' => [['waiter', 18, 24, 30, ['Boris', 'Kevin']], ['kitchen', 16, 23, 30, ['Franck']]]],
            ['days_ago' => 20, 'client' => 'orion', 'title' => 'Déjeuner clients grands comptes', 'place' => 'Orion Télécom, siège de Bonapriso', 'channel' => RequestChannel::Email,
                'shifts' => [['waiter', 11, 15, 0, ['Brenda', 'Kevin']], ['hostess', 11, 15, 0, ['Grace']]]],
            ['days_ago' => 13, 'client' => 'rives', 'title' => 'Séminaire banque, pause-café et déjeuner', 'place' => 'Hôtel Rives du Wouri, Bonanjo', 'channel' => RequestChannel::Phone,
                'shifts' => [['waiter', 8, 16, 45, ['Brenda', 'Boris', 'Junior']], ['hostess', 8, 13, 0, ['Sandrine']]]],
            ['days_ago' => 6, 'client' => 'rives', 'title' => 'Gala annuel des assureurs, 180 invités', 'place' => 'Hôtel Rives du Wouri, Bonanjo', 'channel' => RequestChannel::Phone,
                'shifts' => [['waiter', 17, 23, 30, ['Brenda', 'Junior', 'Boris']], ['bartender', 18, 24, 30, ['Christian']], ['hostess', 18, 22, 0, ['Sandrine', 'Larissa']]],
                'missing' => ['Larissa'], 'pending' => ['Sandrine'], 'adjust' => ['Junior' => [45, 6 * 60, 'Rangement compté 30 minutes.']]],
            ['days_ago' => 1, 'client' => 'orion', 'title' => 'Déjeuner d’équipe commerciale', 'place' => 'Orion Télécom, siège de Bonapriso', 'channel' => RequestChannel::Email,
                'shifts' => [['waiter', 11, 15, 0, ['Brenda', 'Boris']]], 'missing' => ['Brenda', 'Boris']],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function comingEvents(): array
    {
        return [
            // This week: fully staffed.
            ['days_ago' => -3, 'client' => 'elegance', 'title' => 'Mariage Owona – Mballa, 250 invités', 'place' => 'Palais des Congrès, Yaoundé', 'channel' => RequestChannel::Website,
                'shifts' => [['waiter', 16, 23, 30, ['Mireille', 'Brenda', 'Boris', 'Kevin']], ['bartender', 17, 25, 30, ['Christian', 'Junior']], ['hostess', 15, 20, 0, ['Sandrine', 'Aïcha']]]],
            // This week: one place still empty, so the dashboard has something to fill.
            ['days_ago' => -5, 'client' => 'orion', 'title' => 'Lancement du forfait Jeunes', 'place' => 'Carrefour Bonamoussadi, Douala', 'channel' => RequestChannel::WhatsApp,
                'shifts' => [['promoter', 10, 18, 60, ['Larissa', 'Aïcha'], 3], ['hostess', 10, 18, 60, ['Grace']], ['security', 9, 19, 60, ['Emmanuel']]]],
            ['days_ago' => -12, 'client' => 'rives', 'title' => 'Gala de fin d’année', 'place' => 'Hôtel Rives du Wouri, Bonanjo', 'channel' => RequestChannel::Phone,
                'shifts' => [['waiter', 17, 24, 30, ['Brenda', 'Boris'], 4], ['bartender', 18, 25, 30, ['Christian'], 2], ['hostess', 18, 22, 0, [], 3]],
                'status' => RequestStatus::InProgress],
            // Requests nobody has worked on yet.
            ['days_ago' => -9, 'client' => 'wouri', 'title' => 'Renfort week-end, service du soir', 'place' => 'Le Wouri Restaurant, Akwa', 'channel' => RequestChannel::Website,
                'positions' => [['waiter', 2], ['kitchen', 1]], 'status' => RequestStatus::New],
            ['days_ago' => -18, 'client' => 'expo', 'title' => 'Foire de Noël, accueil et sécurité', 'place' => 'Boulevard de la Liberté, Akwa', 'channel' => RequestChannel::Email,
                'positions' => [['hostess', 4], ['security', 3]], 'status' => RequestStatus::New],
            ['days_ago' => -15, 'client' => 'manguiers', 'title' => 'Séminaire reporté par le client', 'place' => 'Résidence Les Manguiers, Bastos', 'channel' => RequestChannel::Phone,
                'positions' => [['reception', 1]], 'status' => RequestStatus::Cancelled],
        ];
    }

    /**
     * Creates one job: the request, a shift per position, the people on it, and (for past jobs) their hours.
     *
     * @param  array<string, mixed>  $e
     * @param  array<string, Client>  $clients
     * @param  array<string, JobRole>  $roles
     * @param  array<string, FreelancerProfile>  $people
     * @param  array<string, User>  $staff
     */
    private function event(array $e, array $clients, array $roles, array $people, array $staff): void
    {
        $day = $this->today->subDays($e['days_ago']);
        $client = $clients[$e['client']];
        $positions = isset($e['shifts'])
            ? array_map(fn (array $s) => ['job_role_id' => $roles[$s[0]]->id, 'quantity' => $s[5] ?? count($s[4])], $e['shifts'])
            : array_map(fn (array $p) => ['job_role_id' => $roles[$p[0]]->id, 'quantity' => $p[1]], $e['positions']);

        $request = app(CreateStaffRequest::class)->handle([
            'client_id' => $client->id,
            'client_contact_id' => $client->contacts()->value('id'),
            'title' => $e['title'],
            'details' => 'Tenue noire et blanche, chaussures noires fermées. Arrivée 30 minutes avant pour le briefing.',
            'location' => $e['place'],
            'starts_on' => $day->toDateString(),
            'ends_on' => $day->toDateString(),
            'channel' => $e['channel'],
            'assigned_to' => ($e['status'] ?? null) === RequestStatus::New ? null : $staff['sales']->id,
        ], $positions, $e['channel'] === RequestChannel::Website ? null : $staff['sales']->id, $e['channel'] === RequestChannel::Website ? 'integration' : 'user');

        $status = $e['status'] ?? RequestStatus::Confirmed;
        if ($status === RequestStatus::New) {
            return;
        }
        if ($status === RequestStatus::Cancelled) {
            app(ChangeRequestStatus::class)->handle($request, RequestStatus::Cancelled, 'Le client a reporté l’événement à une date non fixée.');

            return;
        }

        $requestPositions = $request->positions()->orderBy('created_at')->orderBy('id')->get();
        $assignments = [];

        foreach ($e['shifts'] as $i => [$role, $from, $to, $break, $who]) {
            $shift = Shift::query()->create([
                'staff_request_id' => $request->id,
                'staff_request_position_id' => $requestPositions[$i]->id,
                'starts_at' => $day->setTime($from, 0)->utc(),
                'ends_at' => $day->setTime(0, 0)->addHours($to)->utc(), // 25 means 01:00 the next morning
                'headcount' => $e['shifts'][$i][5] ?? count($who),
                'break_minutes' => $break,
                'location' => $e['place'],
                'status' => ShiftStatus::Confirmed,
            ]);

            foreach ($who as $name) {
                $assignments[$name] = [app(AssignFreelancerToShift::class)->handle($shift, $people[$name], $staff['recruiter'])->load('shift'), $shift];
            }
        }

        app(ChangeRequestStatus::class)->handle($request, RequestStatus::InProgress);
        if ($status === RequestStatus::InProgress) {
            return;
        }
        app(ChangeRequestStatus::class)->handle($request, RequestStatus::Confirmed);

        if ($e['days_ago'] <= 0) {
            return; // still to come
        }

        $review = app(ReviewTimesheet::class);
        foreach ($assignments as $name => [$assignment, $shift]) {
            if (in_array($name, $e['missing'] ?? [], true)) {
                continue;
            }
            [$extra, $approved, $reason] = $e['adjust'][$name] ?? [0, null, null];
            $sheet = app(SubmitTimesheet::class)->handle($assignment, $shift->starts_at, $shift->ends_at->copy()->addMinutes($extra), $shift->break_minutes, $extra > 0 ? 'Resté pour le rangement de la salle.' : null);

            if (! in_array($name, $e['pending'] ?? [], true)) {
                $review->approve($sheet, $staff['manager'], $approved, $reason);
            }
        }

        if (empty($e['missing']) && empty($e['pending'])) {
            Shift::query()->where('staff_request_id', $request->id)->update(['status' => ShiftStatus::Completed]);
            app(ChangeRequestStatus::class)->handle($request->refresh(), RequestStatus::Completed);
        }
    }

    /**
     * Pays everyone's approved hours so far, by mobile money, on the given day.
     *
     * @param  array<string, FreelancerProfile>  $people
     * @param  array<string, User>  $staff
     */
    private function payEveryone(array $people, array $staff, CarbonImmutable $on): void
    {
        foreach ($people as $i => $person) {
            if (! CreatePayment::payable($person->id)->exists()) {
                continue;
            }
            $payment = app(CreatePayment::class)->handle($person, $staff['finance']);
            $method = in_array($i, ['Grace', 'Emmanuel'], true) ? 'bank_transfer' : 'mobile_money';
            app(MarkPaymentPaid::class)->handle($payment, $method, ($method === 'mobile_money' ? 'MP' : 'VIR').$on->format('ymd').'.'.substr(md5($person->id), 0, 8), $on->setTime(16, 30));
        }
    }

    private function announcements(): void
    {
        foreach ([
            ['Bring your ID to every shift', 'Apportez votre pièce d’identité à chaque mission',
                'Customers check identity at the entrance. Arrive 30 minutes early, in the agreed dress code.',
                'Les clients vérifient l’identité à l’entrée. Arrivez 30 minutes en avance, dans la tenue convenue.', 1],
            ['End-of-year season: tell us your days off now', 'Saison des fêtes : indiquez vos indisponibilités dès maintenant',
                'December is our busiest month. Mark the days you cannot work in your profile so we can plan fairly.',
                'Décembre est notre mois le plus chargé. Indiquez dans votre profil les jours où vous ne pouvez pas travailler pour que nous planifiions équitablement.', 3],
            ['Payments every Friday', 'Paiements chaque vendredi',
                'Approved hours are paid every Friday by mobile money (MTN or Orange). Submit your hours the same day as the shift.',
                'Les heures validées sont payées chaque vendredi par mobile money (MTN ou Orange). Déclarez vos heures le jour même.', 10],
        ] as [$titleEn, $titleFr, $bodyEn, $bodyFr, $daysAgo]) {
            Announcement::query()->create(['title_en' => $titleEn, 'title_fr' => $titleFr, 'body_en' => $bodyEn, 'body_fr' => $bodyFr, 'published_at' => now()->subDays($daysAgo)]);
        }
    }

    /** A Cameroon mobile number in the usual written form, e.g. +237 6 77 41 20 83. Fictional. */
    private function mobile(int $i): string
    {
        $prefix = ['77', '99', '55', '96', '70', '69', '50', '75'][$i % 8];

        return sprintf('+237 6 %s %02d %02d %02d', $prefix, (13 * $i + 21) % 100, (37 * $i + 4) % 100, (59 * $i + 17) % 100);
    }

    private function emailFor(string $first, string $last): string
    {
        $ascii = fn (string $s) => strtolower((string) preg_replace('/[^a-z]/i', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $s)));

        return $ascii($first).'.'.$ascii($last).'@demo.test';
    }
}
