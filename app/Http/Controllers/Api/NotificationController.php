<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $userId = auth()->id();
        $query = Notification::where('user_id', $userId);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->boolean('unread_only') || $request->status === 'unread') {
            $query->unread();
        } elseif ($request->status === 'read') {
            $query->read();
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->byType($request->type);
        }

        $totalCount = Notification::where('user_id', $userId)->count();
        $unreadCount = Notification::where('user_id', $userId)->unread()->count();
        $readCount = Notification::where('user_id', $userId)->read()->count();

        $perPage = min(100, max(5, (int) $request->input('per_page', 20)));
        $paginated = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'summary' => [
                'total' => $totalCount,
                'unread' => $unreadCount,
                'read' => $readCount,
            ],
        ]);
    }

    public function show($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json($notification);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        $this->notificationService->markAsRead($id);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead()
    {
        $count = $this->notificationService->markAllAsRead(auth()->id());

        return response()->json(['message' => "Marked {$count} notifications as read", 'count' => $count]);
    }

    public function markMultipleAsRead(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $count = Notification::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['message' => "Marked {$count} notifications as read", 'count' => $count]);
    }

    public function getUnreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->id());

        return response()->json(['count' => $count]);
    }

    public function getStats()
    {
        $userId = auth()->id();
        $systemAlertsData = $this->getSystemAlerts()->getData(true);
        $systemAlertsCount = $systemAlertsData['total_alerts'] ?? 0;

        return response()->json([
            'total' => Notification::where('user_id', $userId)->count(),
            'unread' => Notification::where('user_id', $userId)->unread()->count(),
            'read' => Notification::where('user_id', $userId)->read()->count(),
            'system_alerts_count' => $systemAlertsCount,
            'recent_unread' => Notification::where('user_id', $userId)->unread()->latest()->take(5)->get(),
        ]);
    }

    public function getUsers()
    {
        $users = User::select('id', 'name', 'email')->orderBy('name')->get();
        return response()->json($users);
    }

    public function getSystemAlerts()
    {
        $alerts = [];

        // 1. Low stock & Out of stock alert
        $lowStockCount = Product::whereNotNull('min_stock')->whereColumn('stock_quantity', '<=', 'min_stock')->count();
        $outOfStockCount = Product::where('stock_quantity', '<=', 0)->count();
        if ($lowStockCount > 0 || $outOfStockCount > 0) {
            $sampleProducts = Product::where('stock_quantity', '<=', 0)
                ->select('id', 'name_ar', 'name_en', 'sku', 'stock_quantity')
                ->take(5)
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name_ar ?: $p->name_en,
                    'sku' => $p->sku,
                    'stock_quantity' => $p->stock_quantity,
                ]);

            $alerts[] = [
                'id' => 'alert_low_stock',
                'type' => 'inventory',
                'severity' => $outOfStockCount > 0 ? 'critical' : 'warning',
                'title' => 'تنبيه نقص ونفاد المخزون',
                'title_en' => 'Low & Out of Stock Alert',
                'message' => "يوجد {$outOfStockCount} منتج نافد من المخزون، و {$lowStockCount} منتج بحاجة لإعادة طلب.",
                'message_en' => "{$outOfStockCount} products out of stock, {$lowStockCount} items below reorder point.",
                'count' => $lowStockCount,
                'route' => '/admin/stock',
                'icon' => 'package',
                'items' => $sampleProducts,
            ];
        }

        // 2. Pending Sales Orders alert
        $pendingOrders = SalesOrder::where('status', 'pending')->count();
        if ($pendingOrders > 0) {
            $recentOrders = SalesOrder::where('status', 'pending')
                ->with('customer:id,name')
                ->select('id', 'order_number', 'customer_id', 'total', 'created_at')
                ->latest()
                ->take(5)
                ->get();

            $alerts[] = [
                'id' => 'alert_pending_sales_orders',
                'type' => 'order',
                'severity' => 'warning',
                'title' => 'طلبات بيع بانتظار التأكيد',
                'title_en' => 'Pending Sales Orders',
                'message' => "يوجد {$pendingOrders} طلب بيع معلق بحاجة لموافقة أو تأكيد.",
                'message_en' => "{$pendingOrders} sales orders are pending confirmation.",
                'count' => $pendingOrders,
                'route' => '/admin/sales/sales-orders?status=pending',
                'icon' => 'shopping-cart',
                'items' => $recentOrders,
            ];
        }

        // 3. Confirmed Purchase Orders awaiting receipt
        $confirmedPurchases = PurchaseOrder::where('status', 'confirmed')->count();
        if ($confirmedPurchases > 0) {
            $recentPurchases = PurchaseOrder::where('status', 'confirmed')
                ->with('supplier:id,name')
                ->select('id', 'order_number', 'supplier_id', 'total', 'created_at')
                ->latest()
                ->take(5)
                ->get();

            $alerts[] = [
                'id' => 'alert_purchase_receipts_due',
                'type' => 'warehouse',
                'severity' => 'info',
                'title' => 'أوامر شراء بانتظار استلام البضاعة',
                'title_en' => 'Purchase Orders Awaiting Receipt',
                'message' => "يوجد {$confirmedPurchases} أمر شراء مؤكد بانتظار استلام البضاعة في المستودع.",
                'message_en' => "{$confirmedPurchases} purchase orders are awaiting goods receipt.",
                'count' => $confirmedPurchases,
                'route' => '/admin/purchases/receipts',
                'icon' => 'warehouse',
                'items' => $recentPurchases,
            ];
        }

        // 4. Overdue Invoices
        $overdueInvoices = Invoice::where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->where('due_date', '<', now())
            ->count();
        if ($overdueInvoices > 0) {
            $sampleInvoices = Invoice::where('status', '!=', 'cancelled')
                ->where('due_amount', '>', 0)
                ->where('due_date', '<', now())
                ->with('customer:id,name')
                ->select('id', 'invoice_number', 'customer_id', 'due_amount', 'due_date')
                ->latest('due_date')
                ->take(5)
                ->get();

            $alerts[] = [
                'id' => 'alert_overdue_invoices',
                'type' => 'financial',
                'severity' => 'critical',
                'title' => 'فواتير متأخرة السداد',
                'title_en' => 'Overdue Invoices',
                'message' => "يوجد {$overdueInvoices} فاتورة مبيعات تجاوزت تاريخ الاستحقاق دون سداد كامل.",
                'message_en' => "{$overdueInvoices} invoices are past due date.",
                'count' => $overdueInvoices,
                'route' => '/admin/sales/invoices?status=unpaid',
                'icon' => 'dollar-sign',
                'items' => $sampleInvoices,
            ];
        }

        return response()->json([
            'alerts' => $alerts,
            'total_alerts' => count($alerts),
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function destroy($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    public function destroyMultiple(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $count = Notification::where('user_id', auth()->id())
            ->whereIn('id', $validated['ids'])
            ->delete();

        return response()->json(['message' => "Deleted {$count} notifications", 'count' => $count]);
    }

    public function destroyAllRead()
    {
        $count = Notification::where('user_id', auth()->id())
            ->read()
            ->delete();

        return response()->json(['message' => "Deleted {$count} read notifications", 'count' => $count]);
    }

    public function getPreferences()
    {
        $userId = auth()->id();
        $types = ['order', 'inventory', 'warehouse', 'financial', 'system'];
        $prefs = [];

        foreach ($types as $type) {
            $p = NotificationPreference::where('user_id', $userId)
                ->where('notification_type', $type)
                ->first();

            if (!$p) {
                $p = NotificationPreference::create([
                    'user_id' => $userId,
                    'notification_type' => $type,
                    'email_enabled' => true,
                    'sms_enabled' => false,
                    'push_enabled' => true,
                    'in_app_enabled' => true,
                    'channels' => ['email', 'push', 'in_app'],
                ]);
            }
            $prefs[] = $p;
        }

        return response()->json($prefs);
    }

    public function updatePreferences(Request $request)
    {
        $userId = auth()->id();

        if ($request->has('preferences') && is_array($request->preferences)) {
            $updated = [];
            foreach ($request->preferences as $pref) {
                if (!empty($pref['notification_type'])) {
                    $updated[] = NotificationPreference::updateOrCreate(
                        [
                            'user_id' => $userId,
                            'notification_type' => $pref['notification_type'],
                        ],
                        [
                            'email_enabled' => (bool) ($pref['email_enabled'] ?? false),
                            'sms_enabled' => (bool) ($pref['sms_enabled'] ?? false),
                            'push_enabled' => (bool) ($pref['push_enabled'] ?? false),
                            'in_app_enabled' => (bool) ($pref['in_app_enabled'] ?? true),
                        ]
                    );
                }
            }
            return response()->json($updated);
        }

        $validated = $request->validate([
            'notification_type' => 'required|string',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
        ]);

        $pref = NotificationPreference::updateOrCreate(
            [
                'user_id' => $userId,
                'notification_type' => $validated['notification_type'],
            ],
            $validated
        );

        return response()->json($pref);
    }

    public function indexTemplates(Request $request)
    {
        $query = NotificationTemplate::query();

        if ($request->type) {
            $query->byType($request->type);
        }

        if ($request->active_only) {
            $query->active();
        }

        return response()->json($query->get());
    }

    public function showTemplate($id)
    {
        $template = NotificationTemplate::findOrFail($id);

        return response()->json($template);
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'template_key' => 'required|string|unique:notification_templates,template_key',
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'subject' => 'nullable|string',
            'subject_ar' => 'nullable|string',
            'body' => 'required|string',
            'body_ar' => 'nullable|string',
            'type' => 'required|in:email,sms,push,in_app',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $template = NotificationTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $template = NotificationTemplate::findOrFail($id);

        $validated = $request->validate([
            'template_key' => 'string|unique:notification_templates,template_key,' . $id,
            'name' => 'string',
            'name_ar' => 'nullable|string',
            'subject' => 'nullable|string',
            'subject_ar' => 'nullable|string',
            'body' => 'string',
            'body_ar' => 'nullable|string',
            'type' => 'in:email,sms,push,in_app',
            'variables' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return response()->json($template);
    }

    public function destroyTemplate($id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }

    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required_if:send_to,user|nullable|exists:users,id',
            'send_to' => 'nullable|in:user,all_admins,all_users',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:info,success,warning,error,order,inventory,warehouse,financial,system',
            'route' => 'nullable|string',
            'channels' => 'nullable|array',
            'data' => 'nullable|array',
        ]);

        $data = $validated['data'] ?? [];
        if (!empty($validated['route'])) {
            $data['route'] = $validated['route'];
        }
        if (!empty($validated['channels'])) {
            $data['channels'] = $validated['channels'];
        }

        $type = $validated['type'] ?? 'info';
        $sendTo = $validated['send_to'] ?? ($validated['user_id'] ? 'user' : 'all_admins');

        if ($sendTo === 'all_admins') {
            $notifications = $this->notificationService->notifyAdmins(
                $validated['title'],
                $validated['message'],
                $type,
                $data
            );
            return response()->json(['message' => 'Notification sent to all admins', 'count' => count($notifications)], 201);
        }

        if ($sendTo === 'all_users') {
            $allUserIds = User::pluck('id')->all();
            $notifications = $this->notificationService->sendToUsers(
                $allUserIds,
                $validated['title'],
                $validated['message'],
                $type,
                $data
            );
            return response()->json(['message' => 'Notification sent to all users', 'count' => count($notifications)], 201);
        }

        $notification = $this->notificationService->sendToUser(
            $validated['user_id'],
            $validated['title'],
            $validated['message'],
            $type,
            $data
        );

        return response()->json($notification, 201);
    }

    public function sendBulkNotification(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'in:info,success,warning,error,order,inventory,warehouse,financial,system',
            'data' => 'nullable|array',
        ]);

        $notifications = $this->notificationService->sendToUsers(
            $validated['user_ids'],
            $validated['title'],
            $validated['message'],
            $validated['type'] ?? 'info',
            $validated['data'] ?? []
        );

        return response()->json(['sent' => count($notifications)], 201);
    }
}
