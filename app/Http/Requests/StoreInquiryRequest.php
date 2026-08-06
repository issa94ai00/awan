<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:50',
            'subject' => 'required|in:product_inquiry,price_quote,delivery,technical_support,partnership,other',
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'subject.required' => 'الموضوع مطلوب',
            'subject.in' => 'الموضوع المحدد غير صالح',
            'message.required' => 'الرسالة مطلوبة',
            'message.max' => 'الرسالة يجب أن لا تتجاوز 5000 حرف',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'product_id.exists' => 'المنتج المحدد غير موجود',
            'priority.in' => 'الأولوية المحددة غير صالحة',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'data' => null,
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
