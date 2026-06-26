<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'annualRevenue' => ['required', 'numeric', 'min:0'],
            'cuotaMonthly' => ['required', 'numeric', 'min:0'],
            'monthlySalary' => ['required', 'numeric', 'min:0'],
        ];
    }
}
