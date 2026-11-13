<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CrmPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\PortalPanelProvider;

return [
    AppServiceProvider::class,
    PlatformPanelProvider::class,
    CrmPanelProvider::class,
    PortalPanelProvider::class,
];
