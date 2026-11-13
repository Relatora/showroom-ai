<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;

/**
 * Appointments inherit their permissions from the lead they belong to.
 */
class AppointmentPolicy
{
    public function __construct(private readonly LeadPolicy $leads) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->leads->view($user, $appointment->lead);
    }

    public function create(User $user, Lead $lead): bool
    {
        return $this->leads->update($user, $lead);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->leads->update($user, $appointment->lead);
    }
}
