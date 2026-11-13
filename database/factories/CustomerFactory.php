<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '416-555-01'.fake()->numerify('##'),
            'city' => fake()->randomElement(['Toronto', 'Mississauga', 'Markham']),
            'province' => 'ON',
            'preferred_contact' => 'email',
        ];
    }
}
