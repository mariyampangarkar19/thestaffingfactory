<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Enums\PaymentStatus;
use App\Filament\Crm\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;

final class PaymentsToPay implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('finance.manage')) {
            return null;
        }

        $count = Payment::query()->where('status', PaymentStatus::Pending)->count();

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} payment waiting to be sent|[2,*] payments waiting to be sent', $count),
            __('Pay by mobile money or bank, then mark them paid.'),
            __('Pay'),
            PaymentResource::getUrl(parameters: ['filters' => ['status' => ['value' => PaymentStatus::Pending->value]]]),
            'heroicon-o-banknotes',
        );
    }
}
