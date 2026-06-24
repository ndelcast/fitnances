<?php

namespace Database\Factories;

use App\Enums\ExpectedIncomeStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ExpectedIncome>
 */
class ExpectedIncomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Facture '.fake()->numberBetween(2026001, 2026099),
            'client_name' => fake()->company(),
            'amount' => fake()->numberBetween(200_00, 8_000_00),
            'expected_on' => fake()->dateTimeBetween('now', '+90 days'),
            'status' => ExpectedIncomeStatus::Pending,
            'received_transaction_id' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => ['status' => ExpectedIncomeStatus::Received]);
    }
}
