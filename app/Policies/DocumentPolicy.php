<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\FreelancerProfile;
use App\Models\Payment;
use App\Models\User;

/**
 * Staff need the permission that matches what the document is attached to.
 * A freelancer can open only files on their own profile, or files the agency explicitly shared with them.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->can('documents.view')) {
            return $document->documentable_type !== (new Payment)->getMorphClass() || $user->can('finance.view');
        }

        return $this->ownedByFreelancer($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.manage');
    }

    public function review(User $user, Document $document): bool
    {
        return $user->can('documents.review');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.manage');
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->can('documents.manage');
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    private function ownedByFreelancer(User $user, Document $document): bool
    {
        $profileId = $user->freelancerProfile()->value('id');

        if ($profileId === null) {
            return false;
        }

        if ($document->documentable_type === (new FreelancerProfile)->getMorphClass()) {
            return $document->documentable_id === $profileId
                && ($document->uploaded_by === $user->id || $document->visible_to_freelancer);
        }

        if ($document->documentable_type === (new Payment)->getMorphClass()) {
            return $document->visible_to_freelancer
                && Payment::query()->whereKey($document->documentable_id)->where('freelancer_profile_id', $profileId)->exists();
        }

        return false;
    }
}
