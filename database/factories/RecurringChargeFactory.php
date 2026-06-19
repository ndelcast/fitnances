<?php

namespace Database\Factories;

use App\Enums\ChargeFrequency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RecurringCharge>
 */
class RecurringChargeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'label' => fake()->randomElement(['Loyer bureau', 'Logiciel SaaS', 'Assurance RC Pro', 'Comptable', 'Téléphone']),
            'amount' => fake()->numberBetween(10_00, 1_500_00),
            'frequency' => fake()->randomElement(ChargeFrequency::cases()),
            'next_due_on' => fake()->dateTimeBetween('now', '+1 month'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
