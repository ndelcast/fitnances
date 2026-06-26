<?php

namespace App\Http\Requests;

use App\Enums\ChargeFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RecurringChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'frequency' => ['required', new Enum(ChargeFrequency::class)],
            'day_of_month' => ['required', 'integer', 'min:1', 'max:31'],
            'category_id' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();
        $data['amount'] = (int) round(((float) $data['amount']) * 100);
        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }
}
