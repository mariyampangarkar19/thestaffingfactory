<?php

namespace App\Filament\Portal\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return __('Welcome, :name', ['name' => auth()->user()->freelancerProfile?->first_name ?? auth()->user()->name]);
    }

    public static function getNavigationLabel(): string
    {
        return __('Home');
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
