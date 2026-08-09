# توزيع الطلبات على المستودعات — سلة الموظف والطلب المحلي

**الإصدار:** 1.0
**التاريخ:** 2026-08-09
**الفريق المستهدف:** فريق Laravel (awan_v2)
**الحالة:** منفَّذ على الخادم وواجهة Flutter (جاهز للمراجعة)

---

## 1. نظرة عامة وأهداف

يتيح هذا التصميم لفريق العمل (موظف/أدمن) إنشاء **طلب داخلي محلي** عبر تطبيق Flutter
باستخدام سلة خاصة بالموظف، بحيث:

1. يُختار **مستودع لكل صنف** في السلة — مع **اقتراح تلقائي** من الخادم وقابلية التعديل اليدوي.
2. عند طلب منتج بكمية لا تغطيها مستودع واحد، **تُقسَّم الكمية على عدة مستودعات** ويُحفظ
   التوزيع النهائي (الخطة المؤكَّدة) كـ **تخصيصات** مرتبطة ببند الطلب.
3. يكون **المستودع الرئيسي** (`warehouses.is_primary = 1`) هو الأولوية الافتراضية.

المبدأ الأساسي: **بند الطلبية يحمل الكمية الإجمالية، والتخصيصات تحمل توزيعها على
المستودعات (مجموع كميات التخصيص = كمية البند)**.

---

## 2. قواعد العمل

| # | القاعدة |
|---|---------|
| B1 | كل بند في الطلبية يجب أن يكون له توزيع مكتمل: مجموع كميات `sales_order_item_allocations` للبند يساوي `quantity` الخاص بالبند. |
| B2 | المستودع الرئيسي (`is_primary = true`) يُجرب أولاً، ثم بقية المستودعات حسب الرصيد المتاح تنازلياً. |
| B3 | الرصيد "المتاح" يُحسب عبر accessor النموذج `available_stock = quantity - reserved - damaged - quarantined`. |
| B4 | إذا لم يكفِ المخزون الكلي للكمية المطلوبة، يُعاد التوزيع الجزئي مع `fulfilled = false` و `shortage`، ولا يُرفض الطلب تلقائياً (قرار تجاري). |
| B5 | إذا لم يرسل العميل التوزيع، يقوم الخادم بإنشائه تلقائياً عبر `OrderAllocationService::suggestAllocations`. |
| B6 | الطلب الداخلي يمر بنفس دورة حياة الطلبات: `pending → confirmed → ...`، ويُنشأ `SalesOrderStatusHistory`. |
| B7 | أي تعديل على بنود الطلبية (`PUT .../items`) يعيد بناء التخصيصات مع البنود (حذف ثم إنشاء) داخل معاملة واحدة. |
| B8 | الرصيد لا يُحجز عند الاقتراح — الاقتراح **خطة**؛ الحجز الفعلي يتم في مرحلة التأكيد عبر الآليات الحالية. |

---

## 3. نموذج البيانات

### 3.1 الجدول الجديد: `sales_order_item_allocations`

| العمود | النوع | القيود | الوصف |
|--------|-------|--------|-------|
| `id` | bigint PK | auto | |
| `sales_order_item_id` | bigint FK | `constrained → sales_order_items`, `onDelete: cascade` | بند الطلبية |
| `warehouse_id` | bigint FK | `constrained → warehouses`, `onDelete: restrict` | المستودع |
| `quantity` | integer | default 0 | الكمية المخصصة لهذا المستودع |
| `status` | string | default `pending` | `pending / allocated / picked / fulfilled / cancelled` |
| `created_at` / `updated_at` | timestamps | | |
| **فهرس فريد** | | `unique(sales_order_item_id, warehouse_id)` | تخصيص واحد لكل (بند، مستودع) |

### 3.2 ملف الـ Migration

```php
// database/migrations/2026_08_09_000001_create_sales_order_item_allocations_table.php
Schema::create('sales_order_item_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sales_order_item_id')
        ->constrained('sales_order_items')->onDelete('cascade');
    $table->foreignId('warehouse_id')
        ->constrained('warehouses')->onDelete('restrict');
    $table->integer('quantity')->default(0);
    $table->string('status')->default('pending');
    $table->timestamps();
    $table->unique(['sales_order_item_id', 'warehouse_id']);
});
```

### 3.3 العلاقات

```
SalesOrder 1 ─── * SalesOrderItem 1 ─── * SalesOrderItemAllocation * ─── 1 Warehouse
```

- `SalesOrderItem::allocations()` → `hasMany(SalesOrderItemAllocation)`
- `SalesOrderItemAllocation::warehouse()` → `belongsTo(Warehouse)`
- `SalesOrderItemAllocation::salesOrderItem()` → `belongsTo(SalesOrderItem)`

### 3.4 ثوابت حالة التخصيص (`SalesOrderItemAllocation`)

```php
const STATUS_PENDING    = 'pending';
const STATUS_ALLOCATED  = 'allocated';
const STATUS_PICKED     = 'picked';
const STATUS_FULFILLED  = 'fulfilled';
const STATUS_CANCELLED  = 'cancelled';
```

---

## 4. خوارزمية الاقتراح التلقائي

في `App\Services\OrderAllocationService::suggestAllocations(array $items)`:

```
لكل بند {product_id, quantity}:
  1. جلب المستودعات النشطة التي تحمل رصيداً متاحاً لهذا المنتج
     (warehouse_inventory حيث available_stock > 0 والمستودع is_active = true).
  2. الترتيب:
       a. المستودع الرئيسي أولاً (is_primary = true)
       b. ثم حسب available_stock تنازلياً
       c. ثم حسب id تصاعدياً (استقرار الترتيب)
  3. خوارزمية الجشع:
       remaining = quantity
       لكل مستودع (بالترتيب أعلاه):
           take = min(remaining, available_stock)
           إذا take > 0: أضف تخصيصاً {warehouse_id, quantity: take}
           remaining -= take
       توقف عند remaining = 0.
  4. fulfilled = (remaining == 0)؛ shortage = remaining إن لم يكتمل.
```

> ملاحظة: خوارزمية الجشع بترتيب "الرئيسي ثم الأعلى رصيداً" تمثل اختياراً مقصوداً
> يُركّز على المستودع الرئيسي قبل توزيع الباقي. إن أردتم لاحقاً تحسيناً (مثل مراعاة
> المسافة أو التكلفة) فأضيفوا عامل الترتيب داخل `availableWarehousesFor()` دون تغيير العقد.

---

## 5. عقد الـ API

جميع المسارات أدناه تتطلب `Authorization: Bearer <token>` (نطاق `auth:sanctum`)،
وتُغلَّف الاستجابات بإطار `{success, message, data}`.

### 5.1 اقتراح توزيع المستودعات

**`POST /api/v1/order-allocations/suggest`** — `OrderAllocationController@suggest`

الطلب:

```json
{
  "items": [
    { "product_id": 101, "quantity": 25 },
    { "product_id": 102, "quantity": 3 }
  ]
}
```

الاستجابة `200`:

```json
{
  "success": true,
  "message": "تم إنشاء اقتراح التوزيع بنجاح",
  "data": {
    "suggestions": [
      {
        "product_id": 101,
        "product_name": "ماسورة بلاستيك 4 إنش",
        "quantity": 25,
        "fulfilled": true,
        "shortage": 0,
        "allocations": [
          { "warehouse_id": 1, "warehouse_name": "مستودع رئيسي", "warehouse_code": "MAIN", "is_primary": true,  "quantity": 20, "available_stock": 20 },
          { "warehouse_id": 3, "warehouse_name": "مستودع احمد",   "warehouse_code": "AH",   "is_primary": false, "quantity": 5,  "available_stock": 30 }
        ]
      },
      {
        "product_id": 102,
        "product_name": "صمام أمان",
        "quantity": 3,
        "fulfilled": false,
        "shortage": 1,
        "allocations": [
          { "warehouse_id": 2, "warehouse_name": "مستودع ايوب", "warehouse_code": "AY", "is_primary": false, "quantity": 2, "available_stock": 2 }
        ]
      }
    ]
  }
}
```

قواعد التحقق:

| الحقل | القاعدة |
|-------|---------|
| `items` | required, array, min:1 |
| `items.*.product_id` | required, integer, exists:products,id |
| `items.*.product_variant_id` | nullable, exists:product_variants,id |
| `items.*.quantity` | required, integer, min:1 |

### 5.2 إنشاء طلبية داخلية (سلة الموظف)

**`POST /api/v1/admin/purchase-requests`** — `PurchaseRequestController@adminStore`
(ضمن وسيط `manage_orders`)

> **سبب المسار المميز:** المسار العام `POST /api/v1/purchase-requests` (public `store`)
> مسجَّل قبل مسارات الإدارة، ولأن Laravel يطابق أول مسار متطابق فقط، كان لا بد من
> مسار منفصل `admin/purchase-requests` للموظفين كي لا يصطدم بمسار العميل العام.

الطلب:

```json
{
  "name": "محل الكرامة",
  "phone": "0999999999",
  "email": "k@example.com",
  "address": "دمشق — باب توما",
  "notes": "تسليم على دفعتين",
  "assigned_employee_id": 5,
  "items": [
    {
      "product_id": 101,
      "quantity": 25,
      "allocations": [
        { "warehouse_id": 1, "quantity": 20 },
        { "warehouse_id": 3, "quantity": 5 }
      ]
    }
  ]
}
```

الاستجابة `201` (نفس شكل `adminShow`، مع `allocations` داخل كل بند):

```json
{
  "success": true,
  "message": "تم إنشاء الطلبية بنجاح",
  "data": {
    "id": 912,
    "order_number": "SO-000912",
    "status": "pending",
    "status_text": "معلق",
    "total": 25000,
    "subtotal": 25000,
    "order_date": "2026-08-09",
    "customer": { "id": 44, "name": "محل الكرامة", "phone": "0999999999", "email": "k@example.com", "address": "دمشق — باب توما" },
    "assigned_employee": { "id": 5, "name": "أحمد" },
    "items": [
      {
        "id": 3120,
        "product_id": 101,
        "product_name": "ماسورة بلاستيك 4 إنش",
        "quantity": 25,
        "unit_price": 1000,
        "total": 25000,
        "allocations": [
          { "warehouse_id": 1, "warehouse_name": "مستودع رئيسي", "quantity": 20, "status": "pending" },
          { "warehouse_id": 3, "warehouse_name": "مستودع احمد", "quantity": 5, "status": "pending" }
        ]
      }
    ],
    "invoices": []
  }
}
```

> **سلوك تلقائي:** إذا حُذفت `allocations` من أي بند، يُنشئ الخادم التوزيع تلقائياً
> من `OrderAllocationService` (B5). إذا أُرسلت، يجب أن يساوي مجموع كمياتها كمية البند
> وإلا استجابة `422` برسالة عربية.

قواعد التحقق الإضافية:

| الحقل | القاعدة |
|-------|---------|
| `name` / `phone` | required |
| `email` | nullable, email |
| `assigned_employee_id` | nullable, exists:employees,id |
| `items` | required, array, min:1 |
| `items.*.allocations` | nullable, array |
| `items.*.allocations.*.warehouse_id` | required, exists:warehouses,id |
| `items.*.allocations.*.quantity` | required, integer, min:1 |

السلوك داخل الخادم (معاملة `DB::transaction` واحدة):

1. البحث عن العميل بالهاتف ثم البريد (إن وُجد) أو إنشاؤه — `source = purchase_request`.
2. حساب `subtotal` من أسعار المنتجات × الكميات.
3. إنشاء `sales_orders` مع `created_by = auth()->id()` و `assigned_employee_id`.
4. إنشاء البنود ثم تخصيصاتها (`sales_order_item_allocations`).
5. إنشاء الفاتورة المرتبطة (`invoices` + `invoice_items`).
6. تحديث رصيد العميل (`customer->updateBalance`).
7. ترحيل الفاتورة في الدفتر (`LedgerPostingService`) — **غير قاتل** عند الفشل، مع `accounting_warning` في الاستجابة.
8. تسجيل `SalesOrderStatusHistory`.

### 5.3 تحديث بنود طلبية قائمة

**`PUT /api/v1/admin/purchase-requests/{salesOrder}/items`** — `adminUpdateItems`

الطلب:

```json
{
  "items": [
    { "product_id": 101, "quantity": 30, "allocations": [
        { "warehouse_id": 1, "quantity": 20 },
        { "warehouse_id": 2, "quantity": 10 }
    ] }
  ]
}
```

السلوك:

- حذف البنود والتخصيصات القديمة (cascade) وإعادة البناء داخل معاملة واحدة.
- مزامنة الفواتير المرتبطة بنفس البنود الجديدة.
- الاستجابة بنفس شكل `adminShow`.

### 5.4 قراءة الطلبات (تشمل التخصيصات)

- `GET /api/v1/admin/purchase-requests` → `adminIndex` (كل بند يتضمن `allocations`).
- `GET /api/v1/admin/purchase-requests/{salesOrder}` → `adminShow` (نفس الشيء).

**التوافق الرجعي:** الحقول القديمة (id, product_id, product_name, quantity, unit_price, total)
تبقى كما هي؛ `allocations` إضافة فقط، فتستمر الشاشات القائمة في العمل دون تعديل.

---

## 6. مخطط التدفق

```
الموظف (Flutter)                          الخادم (Laravel)
─────────────────                         ─────────────────
1. يفتح شاشة "طلب جديد" /سلة الموظف
2. يضيف منتجات (بحث + كمية)
        │  POST /order-allocations/suggest
        │──────────────────────────────────▶ 3. يجري الاقتراح الجشع
        │◀──────────────────────────────────    ويعيد توزيع المقترح
4. يعرض الاقتراح (المستودع الرئيسي أولاً) + كمية لكل مستودع
5. الموظف يؤكد أو يعدل التوزيع يدوياً
6. يملأ بيانات العميل ويضغط "إنشاء الطلبية"
        │  POST /admin/purchase-requests
        │  {items: [... {quantity, allocations}]}
        │──────────────────────────────────▶ 7. تحقق + معاملة:
        │                                     بند + تخصيصات + فاتورة + ترحيل
        │◀────────────────────────────────── 8. 201 + الطلبية بالتخصيصات
9. يعرض رقم الطلبية ويُحدِّث قائمة الطلبات
10. عند تعديل البنود لاحقاً → PUT .../items (يعيد بناء التخصيصات)
```

---

## 7. خطوات التطبيق على الخادم

1. تشغيل الـ migration:
   ```bash
   php artisan migrate
   ```
2. الـ routes مُنفذة مسبقاً في `routes/api.php`:
   - `POST /api/v1/order-allocations/suggest`
   - `POST /api/v1/admin/purchase-requests` (ضمن `manage_orders` — مسار مميز كي لا يطابق المسار العام `POST /api/v1/purchase-requests` أولاً)
   - `GET/PUT /api/v1/admin/purchase-requests...` (القراءة تشمل `allocations`)
3. التحقق: `php -l` لكل ملف معدل + `php artisan route:list --name=order-allocations`.

> تم تنفيذ كامل جانب الخادم (`migration`، `OrderAllocationService`،
> `OrderAllocationController`، `adminStore`/`adminUpdateItems`/`adminIndex`/`adminShow`
> مع تخصيصات البنود) واجتازت كل الملفات فحص `php -l`.

---

## 7.1 التطبيق العميل (Flutter) — منفَّذ

| المكوّن | الوصف |
|--------|-------|
| `StaffOrderProvider` | سلة الموظف في الذاكرة + خطة التوزيع + إنشاء الطلبية (`createOrder`) |
| شاشة `StaffOrderCartScreen` | بحث المنتجات + إضافة للسلة + زر "اقتراح التوزيع" + عرض المستودعات لكل بند |
| `AllocationEditorSheet` | محرر توزيع لكل بند (كميات لكل مستودع محدودة بالرصيد المتاح) |
| شاشة `StaffOrderCheckoutScreen` | بيانات العميل + ملخص البنود مع المستودعات + إرسال الطلبية |
| `StaffRepository.suggestAllocations/createOrder` | دعوات الـ API أعلاه |

---

## 8. قائمة اختبار مقترحة

| # | السيناريو | المتوقع |
|---|-----------|---------|
| T1 | منتج رصيده في المستودع الرئيسي يكفي الكمية | تخصيص واحد على المستودع الرئيسي، `fulfilled=true` |
| T2 | الكمية تتجاوز رصيد المستودع الرئيسي وتوجد مستودعات أخرى | توزيع جشع على عدة مستودعات |
| T3 | المخزون الكلي أقل من المطلوب | `fulfilled=false` + `shortage` صحيح |
| T4 | إنشاء طلبية بدون `allocations` | الخادم يقترح التوزيع تلقائياً |
| T5 | إرسال تخصيصات مجموعها لا يساوي الكمية | `422` برسالة عربية |
| T6 | `warehouse_id` غير موجود | `422` (exists) |
| T7 | تحديث بنود طلبية (حذف/إضافة/تغيير كمية) | التخصيصات تُعاد بناؤها، الفاتورة تتزامن |
| T8 | المستودع غير نشط | يستبعد من الاقتراح |
| T9 | `adminShow`/`adminIndex` | كل بند يتضمن `allocations` |
| T10 | رصيد بعد حجز/تالف/حجر | يُستثنى من `available_stock` |

---

## 9. نقاط مفتوحة (توصيات مستقبلية)

- ربط `sales_order_item_allocations` بمنظومة الـ Picking الحالية (استهلاك التخصيص عند
  إنشاء قوائم التجهيز بدلاً من إعادة الحساب).
- حجز فعلي للرصيد عند تأكيد الطلب (استخدام `InventoryService::reserve`).
- دعم تخصيصات المنتجات المركّبة (تفكيكها لمكونات قبل الاقتراح).
- حقول `unit_cost` و`batch_number` و`expiry_date` على التخصيص لدعم FIFO/FEFO.
