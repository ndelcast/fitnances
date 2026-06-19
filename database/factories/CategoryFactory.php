<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(TransactionType::cases()),
            'color' => fake()->hexColor(),
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
