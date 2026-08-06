<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignInquiryRequest;
use App\Http\Requests\BulkInquiryRequest;
use App\Http\Requests\StoreInquiryReplyRequest;
use App\Http\Requests\UpdateInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Models\Inquiry;
use App\Models\InquiryReply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InquiryAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Inquiry::with(['product', 'replies.admin', 'assignedTo']);

        // Search functionality
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%')
                  ->orWhere('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by subject
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by assigned admin
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Filter unassigned
        if ($request->filled('unassigned') && $request->unassigned) {
            $query->whereNull('assigned_to');
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $inquiries = $query->orderByDesc('created_at')->paginate(15);

        // Get statistics
        $statusCounts = Inquiry::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $priorityCounts = Inquiry::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع الاستفسارات بنجاح',
            'data' => [
                'inquiries' => InquiryResource::collection($inquiries->items()),
                'pagination' => [
                    'current_page' => $inquiries->currentPage(),
                    'last_page' => $inquiries->lastPage(),
                    'per_page' => $inquiries->perPage(),
                    'total' => $inquiries->total(),
                    'from' => $inquiries->firstItem(),
                    'to' => $inquiries->lastItem(),
                ],
                'status_counts' => $statusCounts,
                'priority_counts' => $priorityCounts,
            ],
        ]);
    }

    public function show(Request $request, Inquiry $inquiry): JsonResponse
    {
        $inquiry->load('product', 'replies.admin', 'assignedTo', 'user');

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع الاستفسار بنجاح',
            'data' => new InquiryResource($inquiry),
        ]);
    }

    public function storeReply(StoreInquiryReplyRequest $request, Inquiry $inquiry): JsonResponse
    {
        $validated = $request->validated();

        $reply = InquiryReply::create([
            'inquiry_id' => $inquiry->id,
            'admin_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        // Mark inquiry as replied
        $inquiry->markAsReplied();

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الرد بنجاح',
            'data' => [
                'reply' => $reply,
            ],
        ], 201);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): JsonResponse
    {
        $validated = $request->validated();

        $inquiry->update(array_filter($validated));

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الاستفسار بنجاح',
            'data' => new InquiryResource($inquiry->fresh())
        ]);
    }

    public function destroy(Inquiry $inquiry): JsonResponse
    {
        $inquiry->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاستفسار بنجاح'
        ]);
    }

    public function close(Request $request, Inquiry $inquiry): JsonResponse
    {
        $inquiry->close();
        return response()->json([
            'success' => true,
            'message' => 'تم إغلاق الاستفسار بنجاح',
            'data' => new InquiryResource($inquiry->fresh())
        ]);
    }

    public function reopen(Request $request, Inquiry $inquiry): JsonResponse
    {
        $inquiry->reopen();
        return response()->json([
            'success' => true,
            'message' => 'تم إعادة فتح الاستفسار بنجاح',
            'data' => new InquiryResource($inquiry->fresh())
        ]);
    }

    public function assign(AssignInquiryRequest $request, Inquiry $inquiry): JsonResponse
    {
        $validated = $request->validated();

        $inquiry->assignTo($validated['assigned_to']);

        return response()->json([
            'success' => true,
            'message' => 'تم تعيين الاستفسار بنجاح',
            'data' => new InquiryResource($inquiry->fresh())
        ]);
    }

    public function bulkUpdate(BulkInquiryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $updateData = array_filter($validated, function($key) {
            return $key !== 'inquiry_ids';
        }, ARRAY_FILTER_USE_KEY);

        Inquiry::whereIn('id', $validated['inquiry_ids'])->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الاستفسارات بنجاح',
            'data' => [
                'updated_count' => count($validated['inquiry_ids'])
            ]
        ]);
    }

    public function bulkDelete(BulkInquiryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $deletedCount = Inquiry::whereIn('id', $validated['inquiry_ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاستفسارات بنجاح',
            'data' => [
                'deleted_count' => $deletedCount
            ]
        ]);
    }
}
