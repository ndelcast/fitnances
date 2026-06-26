<?php

namespace Database\Factories;

use App\Enums\MovementKind;
use App\Enums\MovementSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Movement>
 */
class MovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cash_flow_plan_id' => null,
            'cash_flow_row_id' => null,
            'category_id' => null,
            'kind' => fake()->randomElement([MovementKind::Income, MovementKind::Expense]),
            'label' => fake()->sentence(3),
            'client_name' => null,
            'amount' => fake()->numberBetween(5_00, 5_000_00),
            'estimated_on' => fake()->dateTimeBetween('-3 months', '+3 months'),
            'paid_at' => null,
            'iva_rate' => null,
            'irpf_rate' => null,
            'has_iva' => true,
            'has_irpf' => false,
            'source' => MovementSource::Manual,
        ];
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'kind' => MovementKind::Income,
            'has_irpf' => true,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['kind' => MovementKind::Expense]);
    }

    public function salary(): static
    {
        return $this->state(fn () => [
            'kind' => MovementKind::Salary,
            'has_iva' => false,
            'has_irpf' => false,
        ]);
    }

    public function tax(): static
    {
        return $this->state(fn () => [
            'kind' => MovementKind::Tax,
            'has_iva' => false,
            'has_irpf' => false,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['paid_at' => now()]);
    }

    public function planned(): static
    {
        return $this->state(fn () => ['paid_at' => null]);
    }
}
