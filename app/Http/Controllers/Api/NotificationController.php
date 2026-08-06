<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
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
        $query = Notification::where('user_id', auth()->id());

        if ($request->unread_only) {
            $query->unread();
        }

        if ($request->type) {
            $query->byType($request->type);
        }

        return response()->json($query->latest()->paginate(20));
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

        return response()->json(['message' => "Marked {$count} notifications as read"]);
    }

    public function getUnreadCount()
    {
        $count = $this->notificationService->getUnreadCount(auth()->id());

        return response()->json(['count' => $count]);
    }

    public function destroy($id)
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    public function getPreferences()
    {
        $preferences = $this->notificationService->getUserPreferences(auth()->id());

        if (!$preferences) {
            $this->notificationService->createDefaultPreferences(auth()->id());
            $preferences = $this->notificationService->getUserPreferences(auth()->id());
        }

        return response()->json($preferences);
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'notification_type' => 'required|in:all,order,inventory,warehouse,financial,system',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'channels' => 'array',
        ]);

        $preferences = $this->notificationService->updatePreferences(auth()->id(), $validated);

        return response()->json($preferences);
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
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'message' => 'required|string',
            'type' => 'in:info,success,warning,error,order,inventory,warehouse,financial,system',
            'data' => 'nullable|array',
        ]);

        $notification = $this->notificationService->sendToUser(
            $validated['user_id'],
            $validated['title'],
            $validated['message'],
            $validated['type'] ?? 'info',
            $validated['data'] ?? []
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
