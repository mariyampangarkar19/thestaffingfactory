<?php

namespace Tests\Feature\I18n;

use Tests\TestCase;

class TranslationsTest extends TestCase
{
    public function test_french_and_english_have_the_same_keys(): void
    {
        $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true);

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($fr))), 'Missing in fr.json');
        $this->assertSame([], array_values(array_diff(array_keys($fr), array_keys($en))), 'Missing in en.json');
    }

    public function test_agency_default_language_is_used(): void
    {
        $tenant = $this->createTenant('tsf'); // default_locale = fr
        $this->get($this->tenantUrl($tenant, 'crm/login'), ['Accept-Language' => ''])->assertSee('lang="fr"', false);
        $this->get($this->tenantUrl($tenant, 'crm/login?lang=en'))->assertSee('lang="en"', false);
    }
}
