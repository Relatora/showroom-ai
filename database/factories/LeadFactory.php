<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\LeadTemperature;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'assigned_user_id' => User::factory(),
            'source' => LeadSource::Website,
            'stage' => LeadStage::Contacted,
            'temperature' => LeadTemperature::Warm,
            'budget_max' => 45000,
            'interest' => 'Looking for an AWD SUV for winter driving, around $45,000.',
            'trade_in' => null,
            'position' => 0,
            'last_contacted_at' => now()->subDay(),
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assigned_user_id' => $user->id]);
    }

    public function inStage(LeadStage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }

    public function stale(): static
    {
        return $this->state(fn () => ['last_contacted_at' => now()->subDays(Lead::STALE_AFTER_DAYS + 2)]);
    }
}
