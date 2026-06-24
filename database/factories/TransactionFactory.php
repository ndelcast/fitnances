<?php

namespace Database\Factories;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->numberBetween(5_00, 5_000_00),
            'label' => fake()->sentence(3),
            'occurred_on' => fake()->dateTimeBetween('-3 months', 'now'),
            'source' => TransactionSource::Manual,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Income]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Expense]);
    }
}
