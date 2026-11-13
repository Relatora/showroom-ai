<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Who can do what with a lead.
 *
 * The visibility rule here deliberately matches Lead::visibleTo(), so a list never shows a lead
 * that would 403 when you click it (and the assistant never cites one either).
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->seesWholePipeline() || $lead->assigned_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    /**
     * Handing a lead to someone else is a manager call. Otherwise reps quietly swap the
     * good ones around.
     */
    public function reassign(User $user, Lead $lead): bool
    {
        return $user->isManager();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isManager();
    }
}
