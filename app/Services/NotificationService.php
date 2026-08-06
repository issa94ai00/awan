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
    public function sendFromTemplate($templateKey, $userId, array $data = [], $locale = 'en'): ?Notification
    {
        $template = NotificationTemplate::active()
            ->byKey($templateKey)
            ->first();

        if (!$template) {
            Log::warning("Notification template not found: {$templateKey}");
            return null;
        }

        $subject = $template->renderSubject($data, $locale);
        $message = $template->render($data, $locale);

        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $subject ?? $template->name,
            'message' => $message,
            'type' => $this->getNotificationTypeFromTemplate($template->type),
            'data' => $data,
        ]);

        // Send based on template type
        if ($template->type === NotificationTemplate::TYPE_EMAIL) {
            $this->sendEmail($userId, $subject, $message, $data);
        } elseif ($template->type === NotificationTemplate::TYPE_SMS) {
            $this->sendSms($userId, $message);
        } elseif ($template->type === NotificationTemplate::TYPE_PUSH) {
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
     * Get notification type from template type
     */
    protected function getNotificationTypeFromTemplate($templateType): string
    {
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
