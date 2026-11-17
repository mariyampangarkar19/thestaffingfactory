<?php

namespace App\Providers;

use App\CountryPacks\CountryPackRegistry;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\Document;
use App\Models\FreelancerProfile;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PlatformAdmin;
use App\Models\Shift;
use App\Models\StaffRequest;
use App\Models\Timesheet;
use App\Models\User;
use App\Reports\ReportRegistry;
use App\Tenancy\QueueTenancy;
use App\Tenancy\TenantContext;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(CountryPackRegistry::class, fn () => new CountryPackRegistry(config('platform.country_packs')));
        $this->app->singleton(ReportRegistry::class, fn () => new ReportRegistry(config('platform.reports')));
    }

    public function boot(): void
    {
        QueueTenancy::register();

        // Day before month, 24-hour clock: how dates are written in Cameroon and most of the world outside the US.
        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat('j M Y')->defaultDateTimeDisplayFormat('j M Y, H:i'));
        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat('j M Y')->defaultDateTimeDisplayFormat('j M Y, H:i'));

        // Long-running workers (queues, Octane) must never carry one agency, or the super-admin connection, into the
        // next request. This runs after every request, whether it succeeded or failed.
        $default = config('database.default');
        $this->app->terminating(function () use ($default): void {
            app(TenantContext::class)->clear();
            DB::setDefaultConnection($default);
        });

        // Catch lazy loading, silently dropped attributes and missing attributes while developing.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::preventLazyLoading($this->app->runningUnitTests());

        // Store short names instead of PHP class names in polymorphic columns.
        Relation::enforceMorphMap([
            'user' => User::class,
            'platform_admin' => PlatformAdmin::class,
            'freelancer_profile' => FreelancerProfile::class,
            'client' => Client::class,
            'staff_request' => StaffRequest::class,
            'assignment' => Assignment::class,
            'document' => Document::class,
            'shift' => Shift::class,
            'timesheet' => Timesheet::class,
            'payment' => Payment::class,
            'note' => Note::class,
        ]);

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('intake', fn (Request $request) => Limit::perMinute(60)->by($request->header('X-Key-Id') ?: $request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
