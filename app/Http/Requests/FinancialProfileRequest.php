<?php

namespace App\Http\Requests;

use App\Enums\FiscalRegime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class FinancialProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:20'],
            'activity' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'regime' => ['required', new Enum(FiscalRegime::class)],
            'iva_default' => ['required', 'numeric', 'min:0', 'max:100'],
            'irpf_default' => ['required', 'numeric', 'min:0', 'max:100'],
            'cuota_monthly' => ['nullable', 'numeric', 'min:0'],
            'surcharge_equivalence' => ['nullable', 'boolean'],
            'intra_community' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();
        $data['cuota_monthly'] = isset($data['cuota_monthly'])
            ? (int) round(((float) $data['cuota_monthly']) * 100)
            : 0;
        $data['surcharge_equivalence'] = $data['surcharge_equivalence'] ?? false;
        $data['intra_community'] = $data['intra_community'] ?? false;

        return $data;
    }
}
