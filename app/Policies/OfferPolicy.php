<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function viewDocument(User $user, Offer $offer): bool
    {
        return $user->can('offers.documents.view') && $this->canAccessBranch($user, $offer);
    }

    public function manageDocument(User $user, Offer $offer): bool
    {
        return $user->can('offers.documents.manage') && $this->canAccessBranch($user, $offer);
    }

    public function generateDocumentDraft(User $user, Offer $offer): bool
    {
        return $user->can('offers.documents.generate-draft') && $this->canAccessBranch($user, $offer);
    }

    public function generateDocumentPrintReady(User $user, Offer $offer): bool
    {
        return $user->can('offers.documents.generate-print-ready') && $this->canAccessBranch($user, $offer);
    }

    private function canAccessBranch(User $user, Offer $offer): bool
    {
        if ($user->can('offers.cross-branch')) {
            return true;
        }

        return $user->branch_id !== null && $user->branch_id === $offer->branch_id;
    }
}
