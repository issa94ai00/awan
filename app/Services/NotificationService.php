<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send notification to a user
     */
    public function sendToUser($userId, $title, $message, $type = 'info', array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);

        // Get user preferences
        $preferences = NotificationPreference::where('user_id', $userId)
            ->where('notification_type', 'all')
            ->first();

        if ($preferences) {
            // Send email if enabled
            if ($preferences->email_enabled) {
                $this->sendEmail($userId, $title, $message, $data);
            }

            // Send SMS if enabled
            if ($preferences->sms_enabled) {
                $this->sendSms($userId, $message);
            }

            // Send push notification if enabled
            if ($preferences->push_enabled) {
                $this->sendPushNotification($userId, $title, $message, $data);
            }
        }

        return $notification;
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(array $userIds, $title, $message, $type = 'info', array $data = []): array
    {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = $this->sendToUser($userId, $title, $message, $type, $data);
        }

        return $notifications;
    }

    /**
     * Send notification using template
     */
    public function sendFromTemplate($templateKey, $userId, array $data = [], $locale = 'ar'): ?Notification
    {
        $template = NotificationTemplate::active()
            ->byKey($templateKey)
            ->first();

        if ($template) {
            $subject = $template->renderSubject($data, $locale) ?? $template->name_ar ?? $template->name;
            $message = $template->render($data, $locale);
            $type = $this->getNotificationTypeFromTemplate($template->type, $templateKey);
            $templateType = $template->type;
        } else {
            $fallback = $this->getFallbackTemplate($templateKey, $data, $locale);
            $subject = $fallback['subject'];
            $message = $fallback['message'];
            $type = $fallback['type'];
            $templateType = NotificationTemplate::TYPE_IN_APP;
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $subject,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);

        // Send based on template type
        if ($templateType === NotificationTemplate::TYPE_EMAIL) {
            $this->sendEmail($userId, $subject, $message, $data);
        } elseif ($templateType === NotificationTemplate::TYPE_SMS) {
            $this->sendSms($userId, $message);
        } elseif ($templateType === NotificationTemplate::TYPE_PUSH) {
            $this->sendPushNotification($userId, $subject, $message, $data);
        }

        return $notification;
    }

    /**
     * Send email notification
     */
    protected function sendEmail($userId, $subject, $message, array $data = []): bool
    {
        try {
            $user = User::find($userId);
            if (!$user || !$user->email) {
                return false;
            }

            Mail::raw($message, function ($mail) use ($user, $subject) {
                $mail->to($user->email)
                    ->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send email to user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS notification
     */
    protected function sendSms($userId, $message): bool
    {
        try {
            $user = User::find($userId);
            if (!$user || !$user->phone) {
                return false;
            }

            // Integrate with SMS service (Twilio, etc.)
            // For now, just log
            Log::info("SMS to {$user->phone}: {$message}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send SMS to user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification
     */
    protected function sendPushNotification($userId, $title, $message, array $data = []): bool
    {
        try {
            // Integrate with push notification service (Firebase, OneSignal, etc.)
            // For now, just log
            Log::info("Push notification to user {$userId}: {$title} - {$message}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send push notification to user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order notification
     */
    public function sendOrderNotification($userId, $orderNumber, $status, $locale = 'en'): ?Notification
    {
        $templateKey = match($status) {
            'confirmed' => 'order_confirmed',
            'shipped' => 'order_shipped',
            'delivered' => 'order_delivered',
            'cancelled' => 'order_cancelled',
            default => 'order_update',
        };

        return $this->sendFromTemplate($templateKey, $userId, [
            'order_number' => $orderNumber,
            'status' => $status,
        ], $locale);
    }

    /**
     * Send low stock notification
     */
    public function sendLowStockNotification($userId, $productName, $currentStock, $minStock, $locale = 'en'): ?Notification
    {
        return $this->sendFromTemplate('low_stock_alert', $userId, [
            'product_name' => $productName,
            'current_stock' => $currentStock,
            'min_stock' => $minStock,
        ], $locale);
    }

    /**
     * Send warehouse notification
     */
    public function sendWarehouseNotification($userId, $warehouseName, $message, $type = 'info'): Notification
    {
        return $this->sendToUser($userId, "Warehouse: {$warehouseName}", $message, $type, [
            'warehouse_name' => $warehouseName,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId): bool
    {
        $notification = Notification::find($notificationId);
        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        return true;
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount($userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->count();
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false)
    {
        $query = Notification::where('user_id', $userId);

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->latest()->paginate($limit);
    }

    /**
     * Delete old notifications
     */
    public function deleteOldNotifications($days = 90): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Notify all administrators
     */
    public function notifyAdmins($title, $message, $type = 'info', array $data = []): array
    {
        $adminIds = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->orWhere('id', 1)->pluck('id')->unique()->all();

        return $this->sendToUsers($adminIds, $title, $message, $type, $data);
    }

    /**
     * Fallback templates when template row is absent
     */
    protected function getFallbackTemplate(string $templateKey, array $data = [], string $locale = 'ar'): array
    {
        $orderNumber = $data['order_number'] ?? $data['number'] ?? '';
        $productName = $data['product_name'] ?? '';
        $currentStock = $data['current_stock'] ?? 0;
        $minStock = $data['min_stock'] ?? 0;

        return match ($templateKey) {
            'order_confirmed' => [
                'subject' => $locale === 'ar' ? "تأكيد الطلب #{$orderNumber}" : "Order #{$orderNumber} Confirmed",
                'message' => $locale === 'ar' ? "تم تأكيد طلب المبيعات #{$orderNumber} بنجاح." : "Sales order #{$orderNumber} has been confirmed.",
                'type' => 'order',
            ],
            'order_shipped' => [
                'subject' => $locale === 'ar' ? "شحن الطلب #{$orderNumber}" : "Order #{$orderNumber} Shipped",
                'message' => $locale === 'ar' ? "تم شحن الطلب #{$orderNumber} إلى العميل." : "Order #{$orderNumber} has been shipped to customer.",
                'type' => 'order',
            ],
            'order_delivered' => [
                'subject' => $locale === 'ar' ? "تسليم الطلب #{$orderNumber}" : "Order #{$orderNumber} Delivered",
                'message' => $locale === 'ar' ? "تم تسليم الطلب #{$orderNumber} بنجاح." : "Order #{$orderNumber} has been delivered successfully.",
                'type' => 'order',
            ],
            'order_cancelled' => [
                'subject' => $locale === 'ar' ? "إلغاء الطلب #{$orderNumber}" : "Order #{$orderNumber} Cancelled",
                'message' => $locale === 'ar' ? "تم إلغاء الطلب #{$orderNumber}." : "Order #{$orderNumber} has been cancelled.",
                'type' => 'order',
            ],
            'low_stock_alert' => [
                'subject' => $locale === 'ar' ? "تنبيه انخفاض المخزون: {$productName}" : "Low Stock Alert: {$productName}",
                'message' => $locale === 'ar'
                    ? "المنتج '{$productName}' وصل إلى رصيد منخفض ({$currentStock}) أقل من نقطة إعادة الطلب ({$minStock})."
                    : "Product '{$productName}' is low in stock ({$currentStock}) below reorder point ({$minStock}).",
                'type' => 'inventory',
            ],
            'out_of_stock' => [
                'subject' => $locale === 'ar' ? "نفاد المخزون: {$productName}" : "Out of Stock: {$productName}",
                'message' => $locale === 'ar' ? "المنتج '{$productName}' نفد من المخزون تماماً." : "Product '{$productName}' is completely out of stock.",
                'type' => 'inventory',
            ],
            'payment_received' => [
                'subject' => $locale === 'ar' ? "استلام دفعة مالية" : "Payment Received",
                'message' => $locale === 'ar' ? "تم تسجيل دفعة جديدة بنجاح." : "A new payment has been recorded.",
                'type' => 'financial',
            ],
            'invoice_created' => [
                'subject' => $locale === 'ar' ? "إصدار فاتورة جديدة" : "New Invoice Issued",
                'message' => $locale === 'ar' ? "تم إنشاء فاتورة جديدة في النظام." : "A new invoice has been issued in the system.",
                'type' => 'financial',
            ],
            default => [
                'subject' => ucwords(str_replace('_', ' ', $templateKey)),
                'message' => "System notification: {$templateKey}",
                'type' => 'info',
            ],
        };
    }

    /**
     * Get notification type from template type or template key
     */
    protected function getNotificationTypeFromTemplate($templateType, $templateKey = null): string
    {
        if ($templateKey) {
            if (str_contains($templateKey, 'order')) return 'order';
            if (str_contains($templateKey, 'stock') || str_contains($templateKey, 'inventory')) return 'inventory';
            if (str_contains($templateKey, 'payment') || str_contains($templateKey, 'invoice') || str_contains($templateKey, 'financial')) return 'financial';
            if (str_contains($templateKey, 'warehouse') || str_contains($templateKey, 'cycle_count')) return 'warehouse';
        }

        return match($templateType) {
            NotificationTemplate::TYPE_EMAIL => 'info',
            NotificationTemplate::TYPE_SMS => 'info',
            NotificationTemplate::TYPE_PUSH => 'info',
            NotificationTemplate::TYPE_IN_APP => 'info',
            default => 'info',
        };
    }

    /**
     * Create default notification preferences for user
     */
    public function createDefaultPreferences($userId): void
    {
        NotificationPreference::updateOrCreate(
            [
                'user_id' => $userId,
                'notification_type' => NotificationPreference::TYPE_ALL,
            ],
            [
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => true,
                'in_app_enabled' => true,
                'channels' => ['email', 'push', 'in_app'],
            ]
        );
    }

    /**
     * Update user notification preferences
     */
    public function updatePreferences($userId, array $preferences): NotificationPreference
    {
        return NotificationPreference::updateOrCreate(
            [
                'user_id' => $userId,
                'notification_type' => $preferences['notification_type'] ?? 'all',
            ],
            $preferences
        );
    }

    /**
     * Get user preferences
     */
    public function getUserPreferences($userId): ?NotificationPreference
    {
        return NotificationPreference::where('user_id', $userId)
            ->where('notification_type', 'all')
            ->first();
    }
}
