<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * French or English, chosen from the switch in the top bar or under the sign-in form.
 * Remembered for the browser session, and on the account when someone is signed in.
 */
final class SwitchLanguageController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('platform.locales'), true), 404);

        $request->session()->put('locale', $locale);
        Filament::auth()->user()?->forceFill(['locale' => $locale])->save();

        return redirect()->to($this->safePreviousUrl($request));
    }

    /** Only ever back to a page on this same address, never to a site named in the Referer header. */
    private function safePreviousUrl(Request $request): string
    {
        $previous = url()->previous();
        $fallback = Filament::getCurrentPanel()?->getUrl() ?? '/';

        return parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : $fallback;
    }
}
