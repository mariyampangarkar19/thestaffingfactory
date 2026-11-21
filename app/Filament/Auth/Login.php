<?php

namespace App\Filament\Auth;

use App\Tenancy\TenantContext;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Sign-in for agency staff and freelancers. Same rules as Filament's own page (rate limit, two-step sign-in);
 * only the wording changes so each audience knows it is in the right place. The side panel is
 * resources/views/filament/partials/auth-aside.blade.php.
 */
class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return __('Welcome back');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        $agency = app(TenantContext::class)->tenant()?->name;

        return filament()->getCurrentPanel()?->getId() === 'portal'
            ? __('Sign in to see your assignments, hours and payments.')
            : __('Sign in to :agency to manage requests, shifts and payments.', ['agency' => $agency ?? '']);
    }

    public function hasLogo(): bool
    {
        return false; // the agency name is already in the side panel
    }
}
