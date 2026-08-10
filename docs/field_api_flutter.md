# Field API — دليل فريق Flutter

واجهة برمجية للتطبيق الميداني: إنشاء طلبيات محلية، وعرض مخزون المستودع، ومتابعة الحركات.

**المسار الأساسي:** `/api/v1`
**المصادقة:** Laravel Sanctum — `Authorization: Bearer <token>`
**الترميز:** UTF-8، والاستجابات كلها JSON.

---

## 0. مبادئ لا بد من فهمها قبل التكامل

**الطلبية الميدانية ليست كياناً منفصلاً.** الطلب المُنشأ من التطبيق هو `sales_order` عادي: نفس الجدول، نفس المراحل، نفس آثار الفاتورة والمخزون والقيود المحاسبية. لا يوجد «مسار طلبات للموبايل» — لأن مسارين يعنيان مجموعتي قواعد تتباعدان مع الوقت.

**كل نقطة نهاية مقيّدة بمستودع.** القيد يُحسب من سجل الموظف المرتبط بالمستخدم (`employees.warehouse_id`)، ولا يُقرأ أبداً من الطلب:

| حالة المستخدم | النطاق |
|---|---|
| له سجل موظف بمستودع | **مقيّد** بذلك المستودع وحده |
| بلا سجل موظف (إدارة/مكتب) | غير مقيّد — كل المستودعات النشطة |
| له سجل موظف بلا مستودع | غير مقيّد (سجل ناقص لا يجب أن يُعطّل التطبيق) |

طلب مستودع خارج النطاق يُرفض بـ **403** ورسالة صريحة، ولا يُحوَّل صامتاً لمستودع آخر.

**الأسعار من الكتالوج لا من التطبيق.** عند إنشاء طلب، `unit_price` **لا يُرسَل** — يُؤخذ من `products.price`. تطبيق قديم أو حمولة معدَّلة لا يجب أن تُعيد تسعير البيع. الخصم يُرسَل صراحةً لكل سطر وهو قابل للتدقيق.

**شكل الاستجابة موحّد:**
```json
{ "success": true, "message": "…", "data": { … } }
```
عند الخطأ: `success:false` مع `message` عربية صالحة للعرض مباشرة للمستخدم.

**رموز الحالة:** `200` نجاح · `201` أُنشئ · `403` خارج النطاق · `422` مرفوض بقاعدة عمل (اعرض `message`) · `401` انتهت الجلسة.

---

## 1. المصادقة

### `POST /api/v1/auth/login`
```json
{ "email": "user@example.com", "password": "••••••" }
```
يعيد `data.token`. خزّنه وأرسله في كل طلب لاحق.

### `POST /api/v1/auth/logout`
يُبطل التوكن الحالي.

---

## 2. الجلسة والشاشة الرئيسية

### `GET /api/v1/field/me`

نداء واحد يكفي لرسم الشاشة الرئيسية — لا داعي لسلسلة طلبات على شبكة الهاتف.

```json
{
  "success": true,
  "data": {
    "profile": {
      "user_id": 4,
      "name": "مندوب اختبار",
      "email": "…",
      "employee_id": 2,
      "job_title": "مندوب مبيعات",
      "is_confined_to_warehouse": true,
      "home_warehouse": { "id": 1, "name": "المستودع الرئيسي", "code": "WH-001", "location_type": "warehouse" }
    },
    "warehouses": [
      { "id": 1, "name": "المستودع الرئيسي", "code": "WH-001", "location_type_text": "مستودع", "is_home": true }
    ],
    "summary": {
      "open_orders": 1,
      "awaiting_confirmation": 1,
      "ready_to_ship": 0,
      "my_open_orders": 0,
      "overdue_orders": 0,
      "low_stock_items": 318,
      "out_of_stock_items": 4
    }
  }
}
```

> إذا كان `is_confined_to_warehouse = true` فأخفِ مُبدّل المستودعات في الواجهة؛ القائمة ستحوي عنصراً واحداً.

---

## 3. المخزون

### `GET /api/v1/field/inventory`

| المعامل | النوع | الوصف |
|---|---|---|
| `warehouse_id` | int | اختياري. الافتراضي مستودع الموظف. خارج النطاق ⇒ 403 |
| `search` | string | بالاسم أو SKU أو الباركود (مطابقة جزئية) |
| `in_stock_only` | bool | المتاح > 0 فقط |
| `low_stock_only` | bool | عند حد إعادة الطلب أو دونه |
| `per_page` | int | افتراضي 30، أقصى 200 |

```json
{
  "data": {
    "warehouse_id": 1,
    "items": [
      {
        "product_id": 305, "sku": "L170", "barcode": null,
        "name": "قلب نحاس 70غ صيني", "price": 2.5,
        "quantity": 1, "reserved": 0, "available": 1,
        "reorder_point": 10, "stock_status": "low",
        "updated_at": "2026-08-08 12:08:24"
      }
    ],
    "pagination": { "current_page": 1, "last_page": 11, "per_page": 30, "total": 318, "has_more_pages": true }
  }
}
```

**`available` هو الرقم القابل للبيع** = `quantity − reserved − damaged − quarantined`. اعرضه واعتمد عليه؛ البيع مقابل `quantity` هو ما يجعل طلبين يَعِدان بنفس القطعة.

`stock_status` محسوب في الخادم (`ok` / `low` / `out`) ليعرض كل عميل نفس اللون لنفس الصف.

### `GET /api/v1/field/inventory/products/{product_id}`

موضع صنف واحد عبر كل المستودعات المتاحة — سؤال «هل أستطيع تأمينه من مكان آخر؟». غير مقيّد بمستودع واحد عمداً.

```json
{ "data": { "product_id": 111, "total_available": 4,
  "warehouses": [ { "warehouse_id": 1, "warehouse_name": "المستودع الرئيسي", "quantity": 5, "reserved": 1, "available": 4 } ] } }
```

### `GET /api/v1/field/inventory/movements`

سجل التتبّع: كل ما دخل وخرج، بكل أسبابه (بيع، استلام، مناقلة، تسوية).

| المعامل | الوصف |
|---|---|
| `warehouse_id` · `product_id` | تصفية |
| `movement_type` | `in` / `out` / `adjustment` |
| `date_from` · `date_to` | `YYYY-MM-DD` |

```json
{ "data": { "movements": [ {
  "id": 499, "product_id": 111, "product_name": "…", "sku": "L17",
  "movement_type": "out", "movement_type_text": "خارج",
  "quantity": 1, "unit_cost": 23, "total_cost": 23,
  "reference": "sales_order", "source": "82",
  "notes": "إخراج مخزون لطلب بيع رقم SO-000082",
  "created_by": "…", "created_at": "2026-08-09 00:23:58"
} ] } }
```

---

## 4. الطلبيات

> **الطلبية تُخدَم من مستودع البائع أولاً.** ما لا يغطيه الفرع يُعرض على
> المستودع الرئيسي بعد موافقة صريحة، ويُكتب في **بند مستقل**. التفاصيل الكاملة
> للقواعد والخوارزمية في [`field-branch-order-sourcing.md`](field-branch-order-sourcing.md).

### `POST /api/v1/field/orders/preview` — أين ستُجلب البضاعة؟

لا يُنشئ شيئاً ولا يحجز. استدعِه أثناء إدخال الكمية ليظهر النقص أمام البائع
قبل الإرسال.

```json
{ "warehouse_id": 1, "items": [ { "product_id": 111, "quantity": 10 } ] }
```

يعيد **200** مع `data.sourcing`:

| الحقل | المعنى |
|---|---|
| `lines[].from_branch` | ما يغطيه مستودعك |
| `lines[].from_supply` | ما سيُضاف من المستودع الرئيسي — **هذه هي كمية البند المكمِّل** |
| `lines[].unavailable` | ما يعجز عنه الاثنان |
| `needs_supply` | اعرض سؤال التكملة |
| `can_fulfil` | `false` = لا فائدة من السؤال؛ الكمية نفسها يجب أن تتغيّر |
| `supply_summary` · `blocked_summary` | نصوص عربية جاهزة للعرض كما هي |

### `POST /api/v1/field/orders` — إنشاء طلبية محلية

```json
{
  "warehouse_id": 1,
  "customer_name": "زبون ميداني",
  "customer_phone": "0999123456",
  "fulfillment_type": "pickup",
  "confirm": false,
  "supply_from_main": false,
  "items": [ { "product_id": 111, "quantity": 2, "discount": 0 } ]
}
```

| الحقل | ملاحظات |
|---|---|
| `warehouse_id` | اختياري؛ الافتراضي مستودع الموظف |
| `customer_id` | إن وُجد العميل. وإلا `customer_name` + `customer_phone` |
| `fulfillment_type` | `ship` / `pickup` / `delivery` — الافتراضي `pickup` |
| `items[].product_id` · `quantity` | إلزامي. **`unit_price` غير مقبول** |
| `items[].discount` | خصم صريح على السطر |
| `discount` · `tax` · `shipping_cost` | على مستوى الطلب |
| `confirm` | `true` = بيع مباشر: يُنشئ ويؤكّد في نداء واحد |
| `supply_from_main` | موافقة البائع على سحب النقص من المستودع الرئيسي. غيابه = لا |

**العميل:** إن لم يُرسل `customer_id` يُبحث بالهاتف أولاً، فلا يتحوّل زبون متكرر إلى سجل جديد كل زيارة.

**`confirm`:** اجعله `true` لبيع الكاونتر (البضاعة تُسلَّم فوراً)، و`false` لعرض سعر ميداني — لأن العرض يجب ألّا يحجز مخزوناً.

**`supply_from_main` إذنٌ لا تعليمةُ توزيع.** أنت تقول "يجوز السحب"؛ الخادم يقرّر
كم ومن أين مقابل الرصيد اللحظي — فخطة حسبها التطبيق قبل دقيقة لا تلتزم بوحدات
بيعت منذ ذلك الحين. إرساله حين يكفي رصيد الفرع غير ضار: لا يُسحب شيء.

يعيد **201** مع `data.order` و`data.lines[]` (كل بند ومصدره) و`data.has_supply_lines`.

**حالتا 422 المتوقعتان — وإحداهما ليست خطأً بل سؤال:**

| `data.reason` | المعنى | ما يفعله التطبيق |
|---|---|---|
| `supply_confirmation_required` | الفرع ناقص والمستودع الرئيسي يغطي الباقي | اعرض نافذة التأكيد، ثم أعد الإرسال بـ `supply_from_main: true` |
| `insufficient_stock` | المستودعان معاً لا يكفيان | اعرض التعذّر مع `blocked_summary`؛ الكمية يجب أن تُعدَّل |

كلتاهما تحملان `data.sourcing` بنفس شكل المعاينة — فابنِ النافذة منه.

> ⚠️ **لا تمنع تجاوز رصيد الفرع في التطبيق.** الهاتف يعرف ما كان على الرف لحظة
> فتح الشاشة لا ما عليه الآن، ولا يعرف شيئاً عن المستودع الرئيسي. دع الخادم يقرّر.

### `GET /api/v1/field/orders`

| المعامل | الوصف |
|---|---|
| `mine` | `1` = المسندة لي فقط (الافتراضي: كل طلبات مستودعي) |
| `status` | `pending`/`confirmed`/`processing`/`shipped`/`delivered`/`cancelled` |
| `open_only` | استبعاد المُسلَّم والملغي |
| `search` | رقم الطلب أو اسم العميل أو هاتفه |

### `GET /api/v1/field/orders/{id}`

يعيد `order` (مع البنود)، و`follow_up`، و`diagnostics`، و`allowed_transitions`، و`history` — نفس الأرقام التي تعرضها شاشات الويب.

- **`follow_up`** — `days_in_stage`, `is_overdue`, `days_overdue`, `is_stalled`, `needs_attention`, `attention_reasons[]`
- **`diagnostics`** — تناقضات الطلب (فاتورة غير مرحّلة، أصناف بلا تكلفة…) بمستويات `error`/`warning`/`info` ولكلٍّ `title` و`detail` و`action`. اعرضها كما هي.
- **`history`** — سجل المراحل: `from_status`, `to_status`, `note`, `by`, `at`

### `POST /api/v1/field/orders/{id}/transition`

```json
{ "status": "shipped", "carrier": "أرامكس", "tracking_number": "TRK-1", "note": "" }
```

الانتقالات المسموحة:

```
pending    → confirmed | cancelled
confirmed  → processing | cancelled
processing → shipped    | cancelled
shipped    → delivered  | cancelled
delivered  → (نهائي)
cancelled  → (نهائي)
```

**اقرأ `allowed_transitions` من الاستجابة ولا تبنِ القائمة في التطبيق** — القواعد تعيش في الخادم.

ماذا يحدث في كل مرحلة:

| الانتقال | الأثر |
|---|---|
| `confirmed` | **حجز** المخزون (لا يخرج) + إنشاء الفاتورة + ترحيل قيد الإيراد |
| `processing` | لا أثر مالي؛ الحجز قائم |
| `shipped` | **خروج** المخزون فعلياً + ترحيل قيد تكلفة البضاعة المباعة |
| `delivered` | ختم التسليم فقط (التحصيل من شاشة الفواتير) |
| `cancelled` | تحرير الحجز أو إرجاع البضاعة + إلغاء الفاتورة + **قيود عكسية** |

⚠️ **الإلغاء يتطلب `note`** وإلا 422. اطلب السبب في الواجهة — يُحفظ في سجل المراحل.

---

## 5. طلبات التزويد من المستودع الرئيسي

**هذه ليست بيعاً.** لا عميل ولا فاتورة ولا إيراد — بضاعة تنتقل بين مستودعين للشركة نفسها. تعمل على `inventory_transfers`، وهي **نفس القائمة التي يشحن منها المكتب**، فطلبٌ من الجوال يظهر في طابور المستودع لا في قائمة خاصة بالموبايل لا يقرؤها أحد.

الدورة موزّعة بين الطرفين عمداً:

| الحالة | المعنى | من ينفّذ |
|---|---|---|
| `pending` | الفرع طلب؛ **الكمية محجوزة في المصدر** فلا تُباع مرتين أثناء الانتظار | الفرع (التطبيق) |
| `in_transit` | المستودع الرئيسي شحن | المكتب |
| `completed` | الفرع أكّد ما وصل فعلاً | الفرع (التطبيق) |

### `GET /api/v1/field/replenishment/suggestions` — ماذا ينقصني؟

يعيد أصناف مستودعك **النافدة أو تحت حد إعادة الطلب**، مرتّبة: النافد أولاً ثم
الأكثر نقصاً. لكل صنف كميةٌ مقترحة ورصيدُ المستودع الرئيسي — فلا يُرسل الموظف
طلباً يُرفض بعد حين.

| معامل | الوصف |
|---|---|
| `warehouse_id` | اختياري؛ الافتراضي مستودع الموظف. خارج نطاقك ⇒ **403** |
| `search` | اسم الصنف أو الرمز أو الباركود |

```jsonc
// data
{
  "warehouse_id": 2,
  "supply_warehouse": { "id": 1, "name": "المستودع الرئيسي" },
  "items": [{
    "product_id": 111,
    "name": "خلاط مغسلة",
    "sku": "SKU-MIXER",
    "available": 4,           // المتاح لديك الآن
    "reorder_point": 10,      // حد إعادة الطلب (المُشغِّل)
    "target_level": 10,       // المستوى الجدير بالاحتفاظ به
    "suggested_quantity": 6,  // اقتراح قابل للتعديل — ليس حكماً
    "supply_available": 50,   // رصيد المستودع الرئيسي
    "supply_covers": 6,       // ما يمكنه فعلاً تلبيته من الاقتراح
    "is_covered": true,
    "urgency": "low"          // "out" | "low"
  }],
  "summary": { "total": 8, "out_of_stock": 2, "low_stock": 6,
               "not_covered": 1, "suggested_units": 45 }
}
```

**الكمية المقترحة** = `target_level - available`، و`target_level` هو الأكبر بين
حد إعادة الطلب و`(متوسط البيع اليومي × مدة التوريد) + مخزون الأمان`. حدّ إعادة
الطلب **مُشغِّل لا هدف**: التزويد إليه بالضبط يُعيد الصنف إلى الحافة ليُنبِّه
مجدداً مع أول بيعة. وإن كان الصنف على الحد تماماً أو بلا هدف مُعرَّف، يُقترح
مقدار حدٍّ كامل — لأن صفراً يعني أن يبدأ الموظف التفكير من الصفر، وهو العمل
الذي وُجدت الشاشة لإزالته.

### `POST /api/v1/field/replenishment`
```json
{
  "items": [
    { "product_id": 111, "quantity": 6 },
    { "product_id": 222, "quantity": 12 }
  ],
  "notes": "نفاد في الفرع"
}
```
- `to_warehouse_id` اختياري — الافتراضي مستودع الموظف
- `from_warehouse_id` اختياري — الافتراضي **المستودع المعلَّم `is_primary`**
- التكلفة تُؤخذ من الكتالوج؛ لا تُرسل من التطبيق

> **أرسل كل الأصناف في طلب واحد.** `items[]` مصفوفة لهذا السبب: الفرع الذي
> يزوّد ثمانية أصناف يجب أن يعطي المستودع **مَهمّة تجهيز واحدة** لا ثمانية
> تحويلات منفصلة يبحث عنها ويغلّفها ويتابعها كلٌّ على حدة.

**عند نقص الرصيد في المصدر — 422:**

```jsonc
{
  "success": false,
  "message": "الكمية غير متوفرة في مستودع \"المستودع الرئيسي\": … عدّل الكميات …",
  "data": {
    "reason": "insufficient_source_stock",
    "shortfalls": [
      { "product_id": 111, "product_name": "…", "requested": 50, "available": 2, "shortfall": 48 }
    ]
  }
}
```

`shortfalls` يحوي **كل** سطر ناقص لا الأول فقط — علِّم به الصفوف المعنية في
الشاشة بدل عرض جملة واحدة يبحث الموظف بعدها عن أيّ أصناف تقصدها. الرفض **ذرّي**:
لا يُكتب تحويل ولا يبقى حجز معلّق على أي سطر.

> استخدم `supply_available` من `suggestions` سقفاً لعدّاد الكمية في الواجهة،
> فسطرٌ واحد يتجاوز الرصيد يُسقِط الطلب كله ومعه بقية الأسطر.

### `GET /api/v1/field/replenishment`
`status` · `open_only` للتصفية. كل عنصر يحمل `can_receive` — **اعتمده لإظهار زر الاستلام** بدل إعادة اشتقاق القاعدة في التطبيق.

### `POST /api/v1/field/replenishment/{id}/receive`
```json
{ "items": [ { "product_id": 111, "quantity_received": 2 } ] }
```
**أرسل ما وصل فعلاً.** الشحنة الناقصة هي الحالة الطبيعية التي وُجدت هذه الخطوة لالتقاطها؛ افتراض وصول الكمية المطلوبة يخترع مخزوناً لم يصل. إن حُذف `items` تُفترض الكمية المشحونة.

الاستلام قبل الشحن ⇒ 422. والاستلام مرتين ⇒ 422 (لا تتضاعف الكميات).

> **ملاحظة تشغيلية:** إن شُحنت 3 ووصلت 2، تخرج 3 من المصدر وتدخل 2 للفرع. الفارق عجز في الطريق ويحتاج تسوية مخزنية — النظام لا يُخفيه.

---

## 6. أخطاء متوقعة

| الرسالة | السبب | التصرف |
|---|---|---|
| `غير مصرّح لك بالعمل على مستودع "…"` | 403، مستودع خارج النطاق | أخفِ المستودع من القائمة |
| `الرصيد غير كافٍ في مستودع "…": …` | 422 عند التأكيد | اعرضها؛ تسرد الأصناف الناقصة بالاسم |
| `لا يمكن نقل الطلب من "…" إلى "…"` | 422، انتقال غير مسموح | اعتمد `allowed_transitions` |
| `يرجى ذكر سبب الإلغاء` | 422 | اطلب السبب |
| `لا يمكن تغيير نوع التنفيذ بعد شحن الطلب` | 422 | عطّل الخيار بعد الشحن |

---

## 7. خريطة الترحيل من نقاط النهاية الحالية في التطبيق

`lib/data/inventory_repository.dart` يستدعي حالياً أربع نقاط **غير موجودة في الخادم إطلاقاً** (تعيد 404). البدائل:

| الاستدعاء الحالي | البديل | ملاحظات |
|---|---|---|
| `GET /inventory` | `GET /field/inventory` | `data.items[]`. أسماء الحقول تغيّرت — انظر أدناه |
| `GET /inventory/requests` | `GET /field/replenishment` | `data.requests[]` |
| `POST /inventory/local-order` | `POST /field/orders` | يحتاج عميلاً و`items[]` بدل صنف واحد |
| `POST /inventory/main-warehouse-request` | `POST /field/replenishment` | `items[]` بدل صنف واحد |
| `GET /inventory/stats` | `GET /field/me` → `summary` | ضمن نداء الإقلاع |
| `PUT /inventory/{id}` | **لا بديل — مقصود** | انظر التحذير |

### تغييرات حقول `InventoryModel`

| الحقل الحالي | الجديد | |
|---|---|---|
| `available_quantity` | `available` | **الرقم القابل للبيع** |
| `reserved_quantity` | `reserved` | |
| `product_name` | `name` | |
| `unit_price` | `price` | |
| `last_updated` | `updated_at` | |
| `warehouse_name` | — | المستودع في جذر الاستجابة |
| `id` | — | استخدم `product_id` |
| — | `stock_status` | `ok`/`low`/`out` محسوب في الخادم |
| — | `reorder_point`, `barcode` | جديد |

> `isLowStock` في الموديل يحسب `< 20%` من الكمية. **استبدله بـ `stock_status`**: النقص يُقاس بحد إعادة الطلب لكل صنف، لا بنسبة مئوية ثابتة تعطي إجابة مختلفة لكل مستودع.

### ⚠️ `PUT /inventory/{id}` لن يُنفَّذ

تعديل الكمية مباشرةً يتجاوز `InventoryService`، فلا تُكتب حركة مخزنية ولا يُعرف من غيّر ولا لماذا — ويختل المخزون عن سجل حركاته بلا أثر. الكمية تتغيّر بمستند فقط: بيع، استلام، تزويد، أو **تسوية مخزنية** مسجَّلة. احذف `updateInventory()` من الـ repository.

---

## 8. متطلب إعداد قبل الإطلاق

التقييد بالمستودع يعمل عبر ربط المستخدم بموظف له مستودع. لكل مستخدم ميداني:

```
employees.user_id      = users.id
employees.warehouse_id = المستودع الذي يعمل منه
```

**بدون هذا الربط سيرى المستخدم كل المستودعات** (يُعامَل كمستخدم مكتب). تحقّق من `profile.is_confined_to_warehouse` في `/field/me` للتأكد.

**ومستودع رئيسي واحد** (`warehouses.is_primary = 1` و`is_active = 1`) — هو مصدر
التكملة. بدونه يعمل كل شيء كما كان، لكن أي نقص في الفرع يصبح رفضاً مباشراً بلا
عرض تكملة. اسم المستودع يصل التطبيق في `profile.supply_warehouse`.

---

## 9. تسلسل نموذجي — بيع كاونتر

```
POST /auth/login                     → token
GET  /field/me                       → المستودع + عدادات الشاشة الرئيسية
GET  /field/inventory?in_stock_only=1&search=…   → اختيار الأصناف
POST /field/orders/preview { items: […] }        → أين ستُجلب البضاعة (لا يحجز)
POST /field/orders  { confirm: true, items: […] }
                                     → حُجز المخزون، أُنشئت الفاتورة، رُحّل القيد
POST /field/orders/{id}/transition { status: "processing" }
POST /field/orders/{id}/transition { status: "shipped" }
                                     → خرج المخزون، رُحّل قيد التكلفة
POST /field/orders/{id}/transition { status: "delivered" }
GET  /field/inventory/movements?product_id=…     → تتبّع الأثر
```

### 9.1 التسلسل حين لا يكفي رصيد الفرع

```
POST /field/orders { quantity: 10, confirm: true }
      ↓ 422  reason: "supply_confirmation_required"
      ↓      sourcing: { from_branch: 3, from_supply: 7, … }
   [نافذة] «الكمية غير كافية في مستودعك — هل تطلب الناقص من المستودع الرئيسي؟»
      ↓ موافقة
POST /field/orders { quantity: 10, confirm: true, supply_from_main: true }
      ↓ 201  has_supply_lines: true
      لبنَدان:  3 × من فرعك        (تخصيص: الفرع)
                7 × «كمية مكمِّلة من المستودع الرئيسي»  (تخصيص: الرئيسي)
      والحجز يتبع الخطة: 3 في الفرع و7 في المستودع الرئيسي.
```
