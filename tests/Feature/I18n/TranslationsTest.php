<?php

namespace Tests\Feature\I18n;

use Symfony\Component\Finder\Finder;
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

    public function test_every_label_used_on_a_screen_has_a_french_translation(): void
    {
        $fr = json_decode((string) file_get_contents(lang_path('fr.json')), true);
        $used = [];

        $files = array_merge(
            iterator_to_array(Finder::create()->files()->in(app_path())->name('*.php')),
            iterator_to_array(Finder::create()->files()->in(resource_path('views'))->name('*.php')),
        );

        foreach ($files as $file) {
            preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $matches);
            foreach ($matches[1] as $key) {
                $used[] = stripslashes($key);
            }
        }

        $this->assertGreaterThan(300, count(array_unique($used)));
        $this->assertSame([], array_values(array_diff(array_unique($used), array_keys($fr))), 'Used in the code but missing in fr.json');
    }

    public function test_agency_default_language_is_used(): void
    {
        $tenant = $this->createTenant('tsf'); // default_locale = fr
        $this->get($this->tenantUrl($tenant, 'crm/login'), ['Accept-Language' => ''])->assertSee('lang="fr"', false);
        $this->get($this->tenantUrl($tenant, 'crm/login?lang=en'))->assertSee('lang="en"', false);
    }
}
