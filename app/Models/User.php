<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\BelongsToTenant;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Traits\HasRoles;

/**
 * A login inside one agency: either agency staff (CRM panel) or a freelancer (portal).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $email
 * @property ?string $locale
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, HasUuids, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'locale', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'crm' => $this->hasAnyRole(Role::staffValues()),
            'portal' => $this->hasRole(Role::Freelancer->value),
            default => false,
        };
    }

    /**
     * Reset links point at the agency's own domain and the right panel (CRM for staff, portal for freelancers).
     */
    public function sendPasswordResetNotification($token): void
    {
        $panel = Filament::getPanel($this->hasAnyRole(Role::staffValues()) ? 'crm' : 'portal');
        $domain = $this->tenant()->first()?->domains()->where('is_primary', true)->value('domain');
        $appUrl = parse_url((string) config('app.url'));
        $scheme = app()->isProduction() ? 'https' : ($appUrl['scheme'] ?? 'http');
        $port = isset($appUrl['port']) && ! app()->isProduction() ? ':'.$appUrl['port'] : '';

        URL::forceRootUrl($domain ? "{$scheme}://{$domain}{$port}" : null);

        try {
            $notification = new ResetPassword($token);
            $notification->url = $panel->getResetPasswordUrl($token, $this);
        } finally {
            URL::forceRootUrl(null);
        }

        $this->notify($notification);
    }
}
