<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\FreelancerProfile;
use App\Models\IntegrationKey;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FreelancerIntakeTest extends TestCase
{
    private Tenant $tenant;

    private IntegrationKey $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTenant('tsf');
        $this->key = $this->makeKey($this->tenant);
    }

    private function makeKey(Tenant $tenant, array $scopes = ['intake:freelancers']): IntegrationKey
    {
        // Keys are issued by the super-admin side; the web app role can only read them.
        return app(TenantContext::class)->run($tenant, function () use ($scopes) {
            $key = new IntegrationKey(['name' => 'WordPress', 'secret' => str_repeat('s', 64), 'scopes' => $scopes]);
            $key->setConnection('pgsql_platform')->save();

            return $key;
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'external_ref' => 'wp-101',
            'full_name' => 'Awa Mbarga Ndzi',
            'email' => 'AWA@example.com',
            'phone' => '+237 678 49 32 70',
            'city' => 'Douala',
            'roles' => ['waiter'],
            'languages' => ['fr'],
            'source' => 'facebook',
            'consented_at' => '2026-11-10T10:00:00Z',
            'quiz' => ['role' => 'waiter', 'score' => 92, 'locale' => 'fr', 'completed_at' => '2026-11-10T10:20:00Z'],
        ], $overrides);
    }

    private function send(array $payload, ?IntegrationKey $key = null, ?int $ts = null, ?string $secret = null, ?string $host = null): TestResponse
    {
        $key ??= $this->key;
        $ts ??= time();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = hash_hmac('sha256', $ts.'.'.$body, $secret ?? str_repeat('s', 64));

        return $this->call('POST', 'http://'.($host ?? 'tsf.staffing.test').'/api/v1/intake/freelancers', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_KEY_ID' => $key->id,
            'HTTP_X_TIMESTAMP' => (string) $ts,
            'HTTP_X_SIGNATURE' => $sig,
        ], $body);
    }

    private function inTenant(callable $fn): mixed
    {
        return app(TenantContext::class)->run($this->tenant, $fn);
    }

    public function test_signed_signup_creates_freelancer_with_graded_quiz(): void
    {
        $this->send($this->payload())->assertCreated();

        $profile = $this->inTenant(fn () => FreelancerProfile::query()->with('quizResults')->sole());
        $this->assertSame('Awa', $profile->first_name);
        $this->assertSame('Mbarga Ndzi', $profile->last_name);
        $this->assertSame('awa@example.com', $profile->email);
        $this->assertSame('B', $profile->quizResults->first()->grade);
        $this->assertSame(['waiter'], $this->inTenant(fn () => $profile->jobRoles()->pluck('slug')->all()));
        $this->assertTrue($profile->quizResults->first()->passed);
        $this->assertSame(1, $this->inTenant(fn () => AuditLog::query()->where('action', 'freelancer.created')->count()));
    }

    public function test_retry_with_same_reference_updates_instead_of_duplicating(): void
    {
        $this->send($this->payload())->assertCreated();
        $this->send($this->payload(['city' => 'Yaoundé']))->assertOk();

        $this->assertSame(1, $this->inTenant(fn () => FreelancerProfile::query()->count()));
        $this->assertSame('Yaoundé', $this->inTenant(fn () => FreelancerProfile::query()->sole()->city));
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $this->send($this->payload(), secret: 'wrong')->assertUnauthorized();
    }

    public function test_old_timestamp_is_rejected(): void
    {
        $this->send($this->payload(), ts: time() - 3600)->assertUnauthorized();
    }

    public function test_replayed_request_is_rejected(): void
    {
        $ts = time();
        $this->send($this->payload(), ts: $ts)->assertCreated();
        $this->send($this->payload(), ts: $ts)->assertStatus(409);
    }

    public function test_key_from_another_agency_does_not_work_on_this_host(): void
    {
        $other = $this->createTenant('other');
        $otherKey = $this->makeKey($other);

        $this->send($this->payload(), key: $otherKey)->assertUnauthorized();
        $this->assertSame(0, $this->inTenant(fn () => FreelancerProfile::query()->count()));
    }

    public function test_key_without_scope_or_revoked_is_rejected(): void
    {
        $this->send($this->payload(), key: $this->makeKey($this->tenant, ['intake:clients']))->assertUnauthorized();

        DB::connection('pgsql_platform')->table('integration_keys')->where('id', $this->key->id)->update(['revoked_at' => now()]);
        $this->send($this->payload())->assertUnauthorized();
    }

    public function test_unknown_host_is_not_found(): void
    {
        $this->send($this->payload(), host: 'nobody.staffing.test')->assertNotFound();
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->send($this->payload(['quiz' => ['score' => 150]]))->assertUnprocessable();
    }
}
