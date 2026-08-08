# دليل إعداد Laravel Echo للتحديثات الفورية
# Laravel Echo Setup Guide for Real-time Updates

## نظرة عامة / Overview
هذا الدليل يشرح كيفية إعداد Laravel Echo مع Pusher لتحقيق تحديثات فورية في واجهة ERP.

This guide explains how to set up Laravel Echo with Pusher to achieve real-time updates in the ERP interface.

---

## المتطلبات / Requirements

### 1. حساب Pusher / Pusher Account

سجل في [Pusher](https://pusher.com/) واحصل على:
- App Key
- App Secret
- App ID
- Cluster

Sign up at [Pusher](https://pusher.com/) and get:
- App Key
- App Secret
- App ID
- Cluster

### 2. تثبيت المكتبات / Install Libraries

```bash
npm install laravel-echo pusher-js
```

---

## إعداد الواجهة الأمامية / Frontend Setup

### 1. إعداد bootstrap.js / Setup bootstrap.js

تم بالفعل في `resources/js/bootstrap.js`:

Already done in `resources/js/bootstrap.js`:

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST ?? `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

### 2. إعداد متغيرات البيئة / Setup Environment Variables

أضف إلى ملف `.env`:

Add to `.env` file:

```env
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

---

## إعداد الخادم الخلفي / Backend Setup

### 1. تثبيت حزمة Pusher PHP / Install Pusher PHP Package

```bash
composer require pusher/pusher-php-server
```

### 2. إعداد config/broadcasting.php / Setup config/broadcasting.php

```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'useTLS' => true,
            'encrypted' => true,
            'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER') . '.pusher.com',
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
        ],
    ],
],
```

### 3. تفعيل Broadcasting / Enable Broadcasting

في `.env`:

In `.env`:

```env
BROADCAST_DRIVER=pusher
```

---

## إنشاء الأحداث / Creating Events

### 1. حدث تحديث المخزون / Stock Updated Event

```php
// app/Events/StockUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $inventory;
    public $warehouseId;

    public function __construct($inventory, $warehouseId)
    {
        $this->inventory = $inventory;
        $this->warehouseId = $warehouseId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('warehouse.' . $this->warehouseId);
    }

    public function broadcastWith()
    {
        return [
            'product_id' => $this->inventory->product_id,
            'quantity' => $this->inventory->quantity,
            'available_quantity' => $this->inventory->available_quantity,
            'message' => "تم تحديث مخزون المنتج {$this->inventory->product->name}",
        ];
    }
}
```

### 2. حدث تنبيه المخزون / Stock Alert Event

```php
// app/Events/StockAlert.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;

    public function __construct($alert)
    {
        $this->alert = $alert;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('dashboard.alerts');
    }

    public function broadcastWith()
    {
        return [
            'alert' => $this->alert,
        ];
    }
}
```

---

## إرسال الأحداث / Dispatching Events

### في Controller / In Controller

```php
use App\Events\StockUpdated;
use App\Events\StockAlert;

class WmsController extends Controller
{
    public function addMovement(Request $request)
    {
        // إضافة الحركة
        // Add movement
        
        // إرسال حدث التحديث
        // Dispatch update event
        broadcast(new StockUpdated($inventory, $warehouseId));
        
        // إرسال تنبيه إذا وصل للحد الأدنى
        // Send alert if reached min stock
        if ($inventory->available_quantity <= $inventory->min_stock) {
            broadcast(new StockAlert([
                'product_id' => $inventory->product_id,
                'warehouse_id' => $warehouseId,
                'message' => "المنتج {$inventory->product->name} في المستودع وصل للحد الأدنى",
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]));
        }
    }
}
```

---

## الاستماع للأحداث في Vue / Listening to Events in Vue

### 1. في Component / In Component

```vue
<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
    warehouseId: Number,
});

onMounted(() => {
    // الاستماع لتحديثات المخزون
    window.Echo.private(`warehouse.${props.warehouseId}`)
        .listen('StockUpdated', (e) => {
            console.log('Stock updated:', e);
            // تحديث البيانات المحلية
            // Update local data
            updateInventory(e);
        });
});

onUnmounted(() => {
    // إلغاء الاشتراك
    window.Echo.leave(`warehouse.${props.warehouseId}`);
});

function updateInventory(data) {
    // تحديث البيانات
    // Update data
}
</script>
```

### 2. في Dashboard / In Dashboard

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const alerts = ref([]);

onMounted(() => {
    // الاستماع لتنبيهات لوحة التحكم
    window.Echo.private('dashboard.alerts')
        .listen('StockAlert', (e) => {
            alerts.value.unshift(e.alert);
            showNotification(e.alert.message);
        });
});

onUnmounted(() => {
    window.Echo.leave('dashboard.alerts');
});

function showNotification(message) {
    // عرض إشعار
    // Show notification
}
</script>
```

---

## إعداد القنوات الخاصة / Setting Up Private Channels

### 1. إنشاء Route للقنوات / Create Channel Route

```php
// routes/channels.php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('warehouse.{id}', function ($user, $id) {
    return $user->hasPermission('view_warehouse', $id);
});

Broadcast::channel('dashboard.alerts', function ($user) {
    return $user->hasRole('admin') || $user->hasPermission('view_dashboard');
});
```

### 2. إعداد المصادقة / Setup Authentication

في `resources/js/bootstrap.js`:

In `resources/js/bootstrap.js`:

```javascript
window.Echo = new Echo({
    // ... other config
    authorizer: (channel, options) => {
        return {
            authorize: (endpoint, headers) => {
                return axios.post(endpoint, {
                    headers,
                    body: JSON.stringify({
                        socket_id: options.socketId,
                        channel_name: channel.name,
                    }),
                });
            },
        };
    },
});
```

---

## اختبار الاتصال / Testing Connection

### 1. اختبار بسيط / Simple Test

```php
// في أي Controller
broadcast(new \App\Events\StockUpdated(
    ['product_id' => 1, 'quantity' => 100],
    1
));
```

### 2. فحص Pusher Dashboard / Check Pusher Dashboard

سجل الدخول إلى [Pusher Dashboard](https://dashboard.pusher.com/) وتحقق من:
- Messages sent
- Connections
- Channels

Login to [Pusher Dashboard](https://dashboard.pusher.com/) and check:
- Messages sent
- Connections
- Channels

---

## استكشاف الأخطاء / Troubleshooting

### المشكلة: لا توجد تحديثات فورية / Problem: No Real-time Updates

**الحل:**

1. تأكد من تفعيل Broadcasting في `.env`:
   ```env
   BROADCAST_DRIVER=pusher
   ```

2. تأكد من صحة مفاتيح Pusher في `.env`

3. تحقق من أن الكلاس يطبق `ShouldBroadcast`

4. تأكد من تشغيل `php artisan queue:work` إذا استخدمت قوائم الانتظار

**Solution:**

1. Ensure Broadcasting is enabled in `.env`
2. Verify Pusher keys in `.env`
3. Check that the class implements `ShouldBroadcast`
4. Ensure `php artisan queue:work` is running if using queues

---

### المشكلة: خطأ في المصادقة / Problem: Authentication Error

**الحل:**

1. تأكد من تعريف Route للقناة في `routes/channels.php`

2. تأكد من أن المستخدم لديه الصلاحية المطلوبة

3. تحقق من إعداد CSRF token

**Solution:**

1. Ensure channel route is defined in `routes/channels.php`
2. Ensure user has required permissions
3. Check CSRF token setup

---

### المشكلة: Echo غير معرف / Problem: Echo Undefined

**الحل:**

تأكد من استيراد `bootstrap.js` في `resources/js/app.js`:

Ensure `bootstrap.js` is imported in `resources/js/app.js`:

```javascript
import './bootstrap';
```

---

## الأحداث المقترحة / Suggested Events

### 1. InventoryMovementCreated
```php
class InventoryMovementCreated implements ShouldBroadcast
{
    public $movement;
    
    public function broadcastOn()
    {
        return new PrivateChannel('warehouse.' . $this->movement->warehouse_id);
    }
}
```

### 2. ProductAssignmentUpdated
```php
class ProductAssignmentUpdated implements ShouldBroadcast
{
    public $assignment;
    
    public function broadcastOn()
    {
        return new PrivateChannel('product.' . $this->assignment->product_id);
    }
}
```

### 3. WarehouseStatusChanged
```php
class WarehouseStatusChanged implements ShouldBroadcast
{
    public $warehouse;
    
    public function broadcastOn()
    {
        return new Channel('warehouses');
    }
}
```

---

## الخلاصة / Summary

تم إعداد Laravel Echo بنجاح للتحديثات الفورية. يمكن الآن:

Laravel Echo has been successfully set up for real-time updates. You can now:

- استقبال تحديثات المخزون فورية
- عرض تنبيهات فورية للمشرفين
- تحديث البيانات دون إعادة تحميل الصفحة
- Receive real-time stock updates
- Display instant alerts for supervisors
- Update data without page reload

---

**تاريخ الإنشاء:** 7 أغسطس 2026  
**الإصدار:** 1.0  
**الحالة:** جاهز للاستخدام
