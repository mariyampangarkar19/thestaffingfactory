<?php

namespace App\Filament\Crm\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Agency dashboard (quote section 1): the widgets live in App\Filament\Crm\Widgets and are discovered automatically,
 * so a new widget appears here without touching this class.
 */
class Dashboard extends BaseDashboard
{
    public function getTitle(): string
    {
        return __('Dashboard');
    }

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }
}
