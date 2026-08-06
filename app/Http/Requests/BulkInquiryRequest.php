<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inquiry_ids' => 'required|array',
            'inquiry_ids.*' => 'exists:inquiries,id',
            'status' => 'nullable|in:new,read,replied,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'inquiry_ids.required' => 'يجب تحديد الاستفسارات',
            'inquiry_ids.array' => 'تنسيق الاستفسارات غير صحيح',
            'inquiry_ids.*.exists' => 'أحد الاستفسارات غير موجود',
            'status.in' => 'الحالة المحددة غير صالحة',
            'priority.in' => 'الأولوية المحددة غير صالحة',
            'assigned_to.exists' => 'المستخدم المحدد غير موجود',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
