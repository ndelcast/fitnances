<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FinancialProfile>
 */
class FinancialProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'urssaf_rate' => 22.00,
            'collects_vat' => false,
            'vat_rate' => null,
            'income_tax_rate' => 0,
            'currency' => 'EUR',
        ];
    }

    public function withVat(float $rate = 20.0): static
    {
        return $this->state(fn () => [
            'collects_vat' => true,
            'vat_rate' => $rate,
        ]);
    }
}
