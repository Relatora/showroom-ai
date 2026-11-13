<?php

namespace Database\Factories;

use App\Enums\BodyStyle;
use App\Enums\Drivetrain;
use App\Enums\Powertrain;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_number' => 'S'.fake()->unique()->numberBetween(5000, 99999),
            'vin' => strtoupper(fake()->unique()->bothify('?????????????????')),
            'year' => fake()->numberBetween(2019, 2026),
            'make' => 'Toyota',
            'model' => 'RAV4 Hybrid',
            'trim' => 'XLE',
            'body_style' => BodyStyle::Suv,
            'powertrain' => Powertrain::Hybrid,
            'drivetrain' => Drivetrain::Awd,
            'seats' => 5,
            'exterior_color' => 'Celestial Silver',
            'exterior_hex' => '#b8bcc2',
            'interior_color' => 'Black leatherette',
            'km' => fake()->numberBetween(0, 120000),
            'price' => fake()->numberBetween(25000, 70000),
            'condition' => VehicleCondition::Used,
            'status' => VehicleStatus::Available,
            'towing_lb' => 1750,
            'range_km' => null,
            'fuel_l_per_100km' => 6.0,
            'features' => ['Heated front seats', 'Apple CarPlay and Android Auto'],
            'description' => 'Compact SUV with standard AWD and hybrid fuel economy.',
            'arrived_on' => now()->subDays(fake()->numberBetween(1, 90)),
        ];
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => VehicleStatus::Sold]);
    }
}
