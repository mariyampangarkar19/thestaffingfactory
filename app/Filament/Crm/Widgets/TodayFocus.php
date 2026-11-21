<?php

namespace App\Filament\Crm\Widgets;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Filament\Crm\Pages\Calendar;
use App\Filament\Crm\Resources\Clients\ClientResource;
use App\Filament\Crm\Resources\FreelancerProfiles\FreelancerProfileResource;
use App\Filament\Crm\Resources\StaffRequests\StaffRequestResource;
use App\Models\User;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Widgets\Widget;

/**
 * The top of the agency dashboard: a greeting, what needs the person today, and the most common shortcuts.
 */
class TodayFocus extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.crm.widgets.today-focus';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $now = now(FilamentTimezone::get());

        return [
            'greeting' => match (true) {
                $now->hour < 12 => __('Good morning, :name', ['name' => strtok($user->name, ' ')]),
                $now->hour < 18 => __('Good afternoon, :name', ['name' => strtok($user->name, ' ')]),
                default => __('Good evening, :name', ['name' => strtok($user->name, ' ')]),
            },
            'today' => $now->translatedFormat('l j F Y'),
            'items' => $this->items($user),
            'shortcuts' => $this->shortcuts($user),
        ];
    }

    /** @return list<AttentionItem> */
    private function items(User $user): array
    {
        return collect(config('platform.dashboard_checks'))
            ->map(fn (string $class) => app($class))
            ->filter(fn ($check) => $check instanceof AttentionCheck)
            ->map(fn (AttentionCheck $check) => $check->check($user))
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<array{label: string, url: string, icon: string}> */
    private function shortcuts(User $user): array
    {
        return array_values(array_filter([
            $user->can('requests.manage') ? ['label' => __('New staffing request'), 'url' => StaffRequestResource::getUrl('create'), 'icon' => 'heroicon-o-plus'] : null,
            $user->can('freelancers.manage') ? ['label' => __('Add a freelancer'), 'url' => FreelancerProfileResource::getUrl('create'), 'icon' => 'heroicon-o-user-plus'] : null,
            $user->can('clients.manage') ? ['label' => __('Add a customer'), 'url' => ClientResource::getUrl('create'), 'icon' => 'heroicon-o-building-office-2'] : null,
            $user->can('requests.view') ? ['label' => __('Open the calendar'), 'url' => Calendar::getUrl(), 'icon' => 'heroicon-o-calendar-days'] : null,
        ]));
    }
}
