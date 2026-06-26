<?php

namespace App\Http\Requests;

use App\Enums\MovementKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('categories')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('type', $this->input('type'))),
            ],
            'type' => ['required', new Enum(MovementKind::class)],
        ];
    }
}
