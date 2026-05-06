<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InquiryController extends Controller
{
    /**
     * Create a new inquiry
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'required|string|max:50',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
                'product_id' => 'nullable|exists:products,id',
            ], [
                'name.required' => 'الاسم مطلوب',
                'phone.required' => 'رقم الهاتف مطلوب',
                'subject.required' => 'الموضوع مطلوب',
                'message.required' => 'الرسالة مطلوبة',
                'email.email' => 'البريد الإلكتروني غير صحيح',
                'product_id.exists' => 'المنتج المحدد غير موجود',
            ]);

            $inquiry = Inquiry::create([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'],
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'status' => Inquiry::STATUS_NEW,
                'product_id' => $validated['product_id'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.',
                'data' => new InquiryResource($inquiry)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Get user's inquiries (authenticated users only)
     */
    public function index(Request $request): JsonResponse
    {
        $inquiries = $request->user()
            ->inquiries()
            ->with('product:id,name_ar,slug')
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'User inquiries retrieved successfully',
            'data' => [
                'inquiries' => InquiryResource::collection($inquiries->items()),
                'pagination' => [
                    'current_page' => $inquiries->currentPage(),
                    'last_page' => $inquiries->lastPage(),
                    'per_page' => $inquiries->perPage(),
                    'total' => $inquiries->total(),
                    'has_more_pages' => $inquiries->hasMorePages(),
                ]
            ]
        ]);
    }

    /**
     * Get specific inquiry details (authenticated users only)
     */
    public function show(Request $request, Inquiry $inquiry): JsonResponse
    {
        // Check if the inquiry belongs to the authenticated user
        if ($inquiry->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this inquiry',
                'data' => null
            ], 403);
        }

        $inquiry->load('product:id,name_ar,slug');

        return response()->json([
            'success' => true,
            'message' => 'Inquiry retrieved successfully',
            'data' => new InquiryResource($inquiry)
        ]);
    }
}
