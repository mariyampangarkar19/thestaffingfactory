<?php

namespace Tests\Feature\Tenancy;

use App\Models\FreelancerProfile;
use App\Tenancy\MissingTenantException;
use App\Tenancy\TenantContext;
use LogicException;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    public function test_models_are_filled_and_filtered_by_current_agency(): void
    {
        [$a, $b] = [$this->createTenant('alpha'), $this->createTenant('beta')];
        $ctx = app(TenantContext::class);

        $awa = $ctx->run($a, fn () => FreelancerProfile::query()->create(['first_name' => 'Awa', 'last_name' => 'A']));
        $this->assertSame($a->id, $awa->tenant_id);

        $this->assertNull($ctx->run($b, fn () => FreelancerProfile::query()->find($awa->id)));
        $this->assertSame(1, $ctx->run($a, fn () => FreelancerProfile::query()->count()));
    }

    public function test_creating_without_an_agency_fails(): void
    {
        $this->expectException(MissingTenantException::class);
        FreelancerProfile::query()->create(['first_name' => 'X', 'last_name' => 'Y']);
    }

    public function test_cannot_create_for_another_agency(): void
    {
        [$a, $b] = [$this->createTenant('alpha'), $this->createTenant('beta')];

        $this->expectException(LogicException::class);
        app(TenantContext::class)->run($a, function () use ($b) {
            $p = new FreelancerProfile(['first_name' => 'X', 'last_name' => 'Y']);
            $p->tenant_id = $b->id;
            $p->save();
        });
    }

    public function test_agency_context_is_restored_after_run(): void
    {
        $a = $this->createTenant('alpha');
        $ctx = app(TenantContext::class);
        $ctx->run($a, fn () => null);

        $this->assertFalse($ctx->has());
    }
}
