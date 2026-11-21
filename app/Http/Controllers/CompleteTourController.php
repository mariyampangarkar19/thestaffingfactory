<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Response;

/**
 * Records that the signed-in person finished or closed the welcome tour, so it does not open again by itself.
 */
final class CompleteTourController
{
    public function __invoke(): Response
    {
        Filament::auth()->user()?->forceFill(['tour_completed_at' => now()])->save();

        return response()->noContent();
    }
}
