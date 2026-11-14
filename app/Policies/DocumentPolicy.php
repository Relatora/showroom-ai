<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Everyone on the floor can read the knowledge base. Only managers change what's in it, because
 * whatever goes in here is what the assistant will tell customers-facing staff is policy.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isManager();
    }
}
