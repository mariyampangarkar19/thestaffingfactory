<?php

namespace App\Dashboard\Checks;

use App\Dashboard\AttentionItem;
use App\Dashboard\Contracts\AttentionCheck;
use App\Filament\Crm\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\User;

final class ExpiringDocuments implements AttentionCheck
{
    public function check(User $user): ?AttentionItem
    {
        if (! $user->can('documents.review')) {
            return null;
        }

        $count = Document::query()->whereNotNull('expires_on')->whereBetween('expires_on', [today(), today()->addDays(30)])->count();

        return $count === 0 ? null : new AttentionItem(
            $count,
            trans_choice('{1} document expires within 30 days|[2,*] documents expire within 30 days', $count),
            __('Ask the freelancer for a new copy before it runs out.'),
            __('View'),
            DocumentResource::getUrl(parameters: ['filters' => ['expiring' => ['isActive' => true]]]),
            'heroicon-o-document-text',
            'info',
        );
    }
}
