<?php

namespace Database\Factories;

use App\Enums\FiscalRegime;
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
            'full_name' => $this->faker->name(),
            'nif' => strtoupper($this->faker->bothify('########?')),
            'activity' => 'Desarrollo de software',
            'province' => 'Barcelona',
            'regime' => FiscalRegime::DirectSimplified,
            'iva_default' => 21,
            'irpf_default' => 15,
            'cuota_monthly' => 29400, // 294,00 € (tarifa plana indicative)
            'surcharge_equivalence' => false,
            'intra_community' => false,
            'currency' => 'EUR',
        ];
    }

    public function newAutonomo(): static
    {
        return $this->state(fn () => [
            'irpf_default' => 7,
            'cuota_monthly' => 8000, // 80,00 € tarifa plana nuevos autónomos
        ]);
    }
}
