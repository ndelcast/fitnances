<?php

namespace App\Http\Requests;

use App\Enums\MovementKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', new Enum(MovementKind::class)],
            'label' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'issued_on' => ['nullable', 'date'],
            'estimated_on' => ['required', 'date'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'iva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'irpf_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'has_iva' => ['nullable', 'boolean'],
            'has_irpf' => ['nullable', 'boolean'],
            'paid' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();
        $data['amount'] = (int) round(((float) $data['amount']) * 100);

        $paid = $data['paid'] ?? false;
        unset($data['paid']);
        $data['paid_at'] = $paid ? now() : null;

        $data['has_iva'] = $data['has_iva'] ?? true;
        $data['has_irpf'] = $data['has_irpf'] ?? false;

        return $data;
    }
}
