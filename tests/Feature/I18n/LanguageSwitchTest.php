<?php

namespace Tests\Feature\I18n;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    public function test_sign_in_page_offers_both_languages_and_switching_changes_the_page(): void
    {
        $tenant = $this->createTenant();
        $login = $this->tenantUrl($tenant, 'crm/login');

        $this->get($login)->assertOk()->assertSee('sp-lang', false)->assertSee($this->tenantUrl($tenant, 'crm/language/fr'), false);

        $this->from($login)->post($this->tenantUrl($tenant, 'crm/language/fr'))->assertRedirect($login);
        $this->get($login)->assertSee('Connectez-vous');

        $this->from($login)->post($this->tenantUrl($tenant, 'crm/language/en'))->assertRedirect($login);
        $this->get($login)->assertSee('Sign in');
    }

    public function test_choice_is_saved_on_the_signed_in_account(): void
    {
        $tenant = $this->createTenant();
        $user = $this->createUser($tenant, Role::Owner->value, ['locale' => 'fr']);

        $this->actingAs($user)->post($this->tenantUrl($tenant, 'crm/language/en'))->assertRedirect();

        $this->assertSame('en', app(TenantContext::class)->run($tenant, fn () => $user->fresh()->locale));
    }

    public function test_unknown_language_is_refused(): void
    {
        $tenant = $this->createTenant();

        $this->post($this->tenantUrl($tenant, 'crm/language/de'))->assertNotFound();
    }

    public function test_never_redirects_to_another_site(): void
    {
        $tenant = $this->createTenant();

        $response = $this->from('https://evil.example/phish')->post($this->tenantUrl($tenant, 'crm/language/en'));

        $this->assertStringNotContainsString('evil.example', (string) $response->headers->get('Location'));
    }
}
