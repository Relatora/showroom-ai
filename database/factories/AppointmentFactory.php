<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'lead_id' => Lead::factory(),
            'user_id' => User::factory(),
            'vehicle_id' => null,
            'type' => AppointmentType::TestDrive,
            'status' => AppointmentStatus::Scheduled,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(45),
        ];
    }
}
