<?php

namespace App\Filament\Support;

use App\Exceptions\DomainRuleViolation;
use Closure;
use Filament\Notifications\Notification;

/**
 * Screens call business actions through this, so a refused rule becomes a clear message instead of an error page.
 */
final class RunsDomainActions
{
    public static function run(Closure $action, ?string $success = null): bool
    {
        try {
            $action();
        } catch (DomainRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();

            return false;
        }

        if ($success !== null) {
            Notification::make()->title($success)->success()->send();
        }

        return true;
    }
}
