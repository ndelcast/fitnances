<?php

namespace Database\Factories;

use App\Enums\MovementKind;
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
            'type' => fake()->randomElement([MovementKind::Income, MovementKind::Expense]),
            'color' => fake()->hexColor(),
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => ['type' => MovementKind::Income]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => MovementKind::Expense]);
    }
}
