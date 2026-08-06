<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CompanyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الشركة مطلوب',
            'contact.required' => 'التواصل مطلوب',
            'address.required' => 'العنوان مطلوب',
            'latitude.numeric' => 'خط العرض يجب أن يكون رقمًا',
            'longitude.numeric' => 'خط الطول يجب أن يكون رقمًا',
        ];
    }
}
