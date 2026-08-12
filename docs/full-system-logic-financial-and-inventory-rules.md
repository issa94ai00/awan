# التوثيق الشامل لمنطق تطبيق AWAN ERP
## مع تفصيل كامل لقواعد التسوية المالية وقواعد حركة المخزون

**الإصدار:** 1.0
**تاريخ الإعداد:** 2026-08-12
**الحالة:** يوثّق حالة الكود الفعلية في فرع `main` وقت الإعداد، بما فيها تعديلات غير مُلتزَمة (uncommitted) قيد التطوير النشط (نظام العملات، وتوجيه الطلبات متعدد المستودعات)
**المنهجية:** قراءة مباشرة للكود المصدري (نماذج/خدمات/متحكمات/اختبارات) وليس تخميناً — كل قاعدة أدناه مرتبطة بموقعها في الكود. حيث توجد وثيقة سابقة أعمق حول موضوع فرعي، يُشار إليها بدل تكرارها بالكامل.

---

## فهرس المحتويات

1. [نظرة عامة على البنية التقنية](#1-نظرة-عامة-على-البنية-التقنية)
2. [الوحدات العامة للتطبيق (نظرة موجزة)](#2-الوحدات-العامة-للتطبيق-نظرة-موجزة)
3. [قواعد حركة المخزون](#3-قواعد-حركة-المخزون)
4. [قواعد التسوية المالية](#4-قواعد-التسوية-المالية)
5. [دورة حياة طلب البيع — الحلقة التي تربط المخزون بالمالية](#5-دورة-حياة-طلب-البيع)
6. [فجوات ومشاكل معروفة تستحق المتابعة](#6-فجوات-ومشاكل-معروفة-تستحق-المتابعة)
7. [ملحق: خريطة الملفات المرجعية](#7-ملحق-خريطة-الملفات-المرجعية)

---

## 1. نظرة عامة على البنية التقنية

| الطبقة | التقنية |
|---|---|
| الخادم | Laravel 12 (PHP 8.2+)، `composer.json` |
| المصادقة | Laravel Sanctum (توكن Bearer) |
| قاعدة البيانات | MySQL (افتراضية في `config/database.php`؛ SQLite تُستخدم فقط في الاختبارات) |
| الواجهة الإدارية | **Vue 3 SPA مستقلة** — وليست Inertia. `vue-router` (تاريخ متصفح كامل) + Pinia (الحالة) + `vue-i18n` (عربي/إنجليزي، RTL/LTR تلقائي) + Element Plus |
| تطبيق الموظف الميداني | Flutter، عبر واجهات `/api/v1/field/*` |
| تطبيق تسوّق منفصل (قيد الإعداد) | تحكّمات بادئتها `Flutter*` (سلة/طلبات/محفظة...) — **غير موصولة فعلياً بـ `routes/api.php`** حتى الآن، تبدو خط عمل موازياً لم يكتمل ربطه |

### 1.1 المصادقة والصلاحيات

يوجد مساران منفصلان تماماً للمصادقة:

| المسار | الآلية | الملف |
|---|---|---|
| **الطاقم/الإدارة** | Sanctum قياسي — `createToken('api_token')->plainTextToken`، وكل الجلسات القديمة تُبطَل عند تسجيل الدخول أو تغيير كلمة المرور (جلسة واحدة نشطة فقط) | `AuthController.php` |
| **العملاء** | **ليس Sanctum** — توكن عشوائي 80 محرفاً يُخزَّن في `customers.auth_token` ويُقارَن يدوياً من `bearerToken()` | `CustomerAuthController.php` |

نموذج الصلاحيات **مخصَّص بالكامل** (لا يُستخدم `spatie/laravel-permission`):
- `User` يحمل `is_admin` (يتجاوز كل فحص صلاحية) + `role_id` (علاقة بـ `Role`) + `roles()` (متعدد-لمتعدد احتياطي).
- `Role` ⟷ `Permission` عبر `permission_role`؛ لكل صلاحية حقل `module` للتجميع.
- الوسائط (`middleware`): `admin` (`IsAdmin`)، `permission` (`CheckPermission` — يفحص `is_admin` ثم `hasPermission()`)، `manage_orders` (`EnsureCanManageOrders`).
- **ملاحظة**: تحكّمات `Admin\RoleController`/`PermissionController` موجودة في الكود لكن **غير مسجَّلة** في `routes/api.php` — شاشات `/admin/roles` و`/admin/permissions` في الواجهة موجودة لكن بلا نقاط API مقابلة مكتملة الربط بعد.
- `Employee` منفصل عن `User` (علاقة 1:1 اختيارية)، ويحمل `warehouse_id` — هو ما يحدد نطاق تطبيق الموظف الميداني.

### 1.2 خريطة مجموعات المسارات الرئيسية (`routes/api.php`، بادئة `v1`)

| المجموعة | الوصف |
|---|---|
| عامة (بلا مصادقة) | الرئيسية، الفئات، المنتجات، البحث، الاستفسارات، طلبات الشراء العامة، تسجيل العملاء، العملات |
| `auth:sanctum` → `field/*` | تطبيق الموظف الميداني (جلسة، مخزون، تزويد، طلبات) |
| `auth:sanctum` → `admin/*` | الجزء الأكبر: منتجات، MRP، انتقاء، منتجات مركّبة، عروض خاصة، فئات، موردون/أوامر شراء، موظفون، حضور، إجازات، مخزون، محاسبة |
| `admin/settings` + `admin/currencies` | الإعدادات والعملات (جديد) |
| `manage_orders` | إجراءات إدارية على طلبات الشراء الداخلية (سلة الموظف) |
| مبيعات | عروض أسعار، طلبات بيع، **توجيه الطلبات (`order-routing`)**، مصدر البضاعة (`sourcing`) |
| مالية | دفعات، مصروفات، إيصالات استلام شراء |
| مرتجعات | RMA، إشعارات دائن، نظرة عامة على العميل |
| تحليلات/إشعارات/سير عمل/تدقيق/رواتب | مجموعات ختامية |

---

## 2. الوحدات العامة للتطبيق (نظرة موجزة)

### 2.1 الكتالوج

- **Product** — الكيان التجاري المركزي (اسم/وصف ثنائي اللغة، سعر، تكلفة، ضريبة، حدود مخزون). يدعم كونه "منتجاً مركّباً" عبر `parentComponents()`/`childComponents()`.
- **ProductVariant** — متغيرات (لون/مقاس/مادة) بسعر/تكلفة/مخزون مستقلين.
- **ProductUnit** — وحدات بديلة لكل منتج (كرتون مقابل قطعة) بمُعامل تحويل ومُعامل سعر.
- **Category** — شجرة فئات ذاتية العلاقة.
- **ProductBatch** — تتبّع دفعات (تصنيع/انتهاء صلاحية) لكل (منتج، متغير، مستودع).
- **ProductSerialNumber** — تتبّع رقم تسلسلي لكل وحدة، بدورة حياة كاملة (`in_stock → reserved → sold`, بالإضافة إلى `damaged/lost/quarantined`).

فالكتالوج يدعم ثلاثة مستويات دقة: منتج عادي ← متغيرات ← تتبّع دفعة/تسلسلي، وكلها تُقاس على مستوى المستودع.

### 2.2 العملاء وCRM

`Customer` (رصيد/حد ائتمان)، `Contact` (جهات اتصال فرعية متعددة الأشكال تخدم العملاء والموردين معاً)، `Address`، `Quote` (عرض سعر يتحول لطلب بيع)، `SalesContract` (اتفاقية طويلة الأمد بحد قيمة وخصم)، `Inquiry` (استفسارات الزوار).

### 2.3 المشتريات

`Supplier` (رصيد/حد ائتمان/مهلة توريد) ← `PurchaseOrder` (دورة حياة: `pending → confirmed → ordered → received → cancelled`) ← `PurchaseReceipt` (استلام فعلي، مُفصَّل في §3.12).

### 2.4 نقطة البيع (POS)

`PosController` خدمة مساندة خفيفة (بحث سريع عن منتج، عملاء عابرون) — **لا يوجد عندها نقطة "إنشاء بيع" خاصة بها**؛ البيع الفعلي يمر عبر `InvoiceController` كأي فاتورة عادية.

### 2.5 التطبيق الميداني (سطحان منفصلان)

| السطح | الغرض | الحالة |
|---|---|---|
| `Api/Field/*` (`FieldSessionController`, `FieldInventoryController`, `FieldReplenishmentController`, `FieldOrderController`) | تطبيق مندوب المبيعات الميداني — طلبات بيع حقيقية عبر `SalesOrderWorkflowService`، تزويد بين الفروع | **نشط ومربوط بالكامل**، موثّق بالتفصيل في `docs/field-branch-order-sourcing.md` و`docs/replenishment-transfer-lifecycle.md` |
| `Api/Flutter*Controller` (سلة، طلبات، دفع، محفظة، تقييمات، إشعارات، مستخدم) | تطبيق تسوّق منفصل بنماذج بيانات خاصة به (`Order`, `CartItem` مختلفة عن `SalesOrder`) | **غير مربوط بـ `routes/api.php`** — يبدو خطاً موازياً/قديماً أو قيد الإعداد لم يُستكمل ربطه |

### 2.6 الموارد البشرية

`Employee` (السجل الأساسي، مرتبط اختيارياً بـ `User` و`Warehouse`) ← `Attendance` (حضور/انصراف) و`LeaveRequest` (إجازات بموافقة) و`Payroll` (رواتب: أساسي + إضافي + مكافآت − استقطاعات = `net_salary`، ترقيم تلقائي `PAY-######`).

### 2.7 الإشعارات والإعدادات والتدقيق

- `Setting` — مخزن مفتاح/قيمة عام (`settings` جدول) يغذّي الإعدادات العامة والعملة الافتراضية.
- `AuditService`/`AuditLog` — سجل تدقيق عام لكل فعل حساس (إنشاء/تعديل/حذف/دخول/تصدير...) بفروقات القيم القديمة/الجديدة و`module` مصنَّف.
- `NotificationService`/`Notification`/`NotificationPreference` — إشعارات متعددة القنوات (بريد/SMS/دفع) حسب تفضيل كل مستخدم.

### 2.8 أحدث إضافة في الواجهة: وحدة العملات

`git diff` على `resources/js/router/index.js` و`AdminSidebar.vue` يُظهر مساراً وشاشة جديدين (`/admin/currencies` ← `views/admin/settings/Currencies.vue`) أُضيفا للتو، مدعومَين بملفات خلفية جديدة بالكامل (`CurrencyController`, `Currency`, `CurrencyRate`, `CurrencyService`) — مُفصَّلة في §4.7.

---

## 3. قواعد حركة المخزون

### 3.1 النموذج المحوري: `WarehouseInventory`

سطر واحد لكل (مستودع، منتج[، متغير][، حاوية]). **الثلاثة دِلاء (buckets) + المحجوز**:

```
quantity (الإجمالي على الرف) = available_quantity (سليم) + damaged_quantity (تالف) + quarantined_quantity (محجور/قيد المعاينة)
sellable (القابل للبيع فعلياً) = max(0, available_quantity − reserved_quantity)
```

`reserved_quantity` تُقتطع من `available_quantity` فقط، وليس من `quantity` مباشرة — الحجز **لا يحرّك** أي دلو، فقط "يُقفل" جزءاً مما هو موجود سلفاً ضمن الدلو السليم.

#### القاعدة المحورية الجديدة: توحيد تعريف "المتاح"

قبل التعديل الحالي (غير المُلتزَم بعد) كانت **أربع** صيغ متضاربة لـ"المتاح" منتشرة في الكود:

| الصيغة القديمة | أين استُخدمت | العيب |
|---|---|---|
| `available_quantity − reserved_quantity` | بوابة البيع الفعلية (`InventoryService`) | هذه الصحيحة — أصبحت المرجع الموحَّد |
| `quantity − reserved − damaged − quarantined` | شاشة المخزون القديمة | تتطابق عددياً مع الصحيحة فقط إذا حُفظت المعادلة الأساسية |
| `quantity − reserved_quantity` | شاشة رصيد WMS القديمة | تتجاهل التالف والمحجور كلياً |
| `available_quantity` وحدها | تعيينات المنتج-مستودع / MRP / المنتجات المركّبة القديمة | تتجاهل الحجز بالكامل — **تُبالغ** فيما هو قابل للبيع فعلياً |

**الحل الموحَّد الآن**: تعبير SQL واحد `WarehouseInventory::availableSql()` يُعاد استخدامه في كل مكان:
```sql
CASE WHEN available_quantity - reserved_quantity > 0
     THEN available_quantity - reserved_quantity ELSE 0 END
```
وaccessor مطابق `getAvailableStockAttribute()`. هذا التوحيد امتدّ ليصحّح: `ProductWarehouseAssignment::available_stock`، تنبيهات وشاشات `WmsController`، `MrpService`، `CompositeProductService`، `ProductWarehouseAssignmentController::updateStockBalance`.

**حقول أخرى على النموذج**: `reorder_point`, `safety_stock`, `lead_time_days`, `average_daily_sales`, `bin_id`, `batch_number`, `expiry_date`, `serial_numbers` (مصفوفة), `cost_basis` (enum `FIFO|FEFO|LIFO` — ⚠️ انظر الاستخدام المزدوج الخاطئ في §6), `last_counted_at`, `count_variance`, `auto_reorder_enabled`.

- `isBelowReorderPoint()`: `available_stock < reorder_point`.
- `calculateDynamicReorderPoint()`: `ceil(متوسط البيع اليومي × مهلة التوريد + مخزون الأمان)`.
- `updateAverageDailySales()`: متوسط حركات الخروج آخر 30 يوماً.

> **لا توجد قيود على مستوى النموذج تفرض معادلة الدِلاء الأساسية عند الكتابة** — فقط أداة الفحص `inventory:check` (§3.15) تكتشف الانحراف بعد وقوعه.

### 3.2 خدمة المخزون المركزية: `InventoryService`

**مسار الكتابة الوحيد المُلزَم للمخزون.** توثيق الرأس: تحافظ على ثلاثة سجلات متزامنة داخل معاملة واحدة: `stock_movements` (سجل التدقيق)، `warehouse_inventory` (الرصيد الفعلي)، `products.stock_quantity` (كاش إجمالي — عبر hook على `StockMovement`، لا تلمسه هذه الخدمة مباشرة كي لا يُحتسَب مرتين).

#### الدوال العامة

| الدالة | الوصف |
|---|---|
| `issue()` | إخراج (كمية سالبة) → `TYPE_OUT` |
| `receive()` | إدخال (كمية موجبة) → `TYPE_IN` |
| `adjust()` | تصحيح موقّع من جرد فعلي — **يفرض `allow_negative=true`** ("الجرد بيان حقيقة، لا يُرفض") |
| `shipReserved()` | `issue(..., consume_reserved: true)` — إخراج يستهلك حجزاً قائماً بدل طلب توفر جديد |
| `transfer()` | نقل ذري كامل: `issue` (مفتاح `{key}:out`) + `receive` (مفتاح `{key}:in`) داخل معاملة واحدة |
| `reserve()` | يحجز بدون خطأ — يُرجع `bool` |
| `release()` | يُطلق الحجز بدون خطأ (no-op إن ≤ 0) |
| `sellableQuantity()` | مجموع "القابل للبيع" عبر السطور المطابقة |

#### `move()` — الخوارزمية الأساسية (يستدعيها `issue`/`receive`/`adjust`)

```
إن كانت الكمية الموقّعة = صفر: لا شيء.

فحص idempotency: إن وُجد movement_key مطابق مسبقاً → إرجاع الحركة القائمة دون تكرار.

داخل DB::transaction:
  إعادة فحص idempotency مع lockForUpdate (سباق التزامن).
  قفل سطر (منتج، مستودع[، حاوية]) FOR UPDATE، أو إنشاؤه صفرياً إن غاب.
  تحديد الدلو الهدف (available | damaged | quarantined).

  بوابة التوفر (فقط عند كمية سالبة وblock الرفض allow_negative=false):
      المتاح داخل الدلو = قيمة الدلو الحالية
      إن كان الدلو "available" وليس استهلاكاً لحجز (consume_reserved=false):
          يُطرح reserved_quantity من الفحص (الوحدات المحجوزة لا تخرج كبيع عادي)
      إن نقص المتاح عن المطلوب → رمي استثناء عربي (منتج/مستودع/متاح/مطلوب)
      إن استهلاك حجز: ينقص reserved_quantity بقدر المُخرَج (بحد أدنى صفر)

  تطبيق الحركة: quantity += الكمية، الدلو += الكمية.

  أثر تكلفة FIFO (داخل نفس القفل):
      كمية سالبة → InventoryCostingService::consume() يُرجع التكلفة الحقيقية ويستبدل أي unit_cost مُرسَل من المستدعي
      كمية موجبة → InventoryCostingService::addLayer() يفتح طبقة تكلفة جديدة

  كتابة سطر StockMovement (الكمية المطلقة لـ in/out، والموقَّعة لـ adjustment كي يظهر النقص سالباً في السجل).
  لا تُلمَس products.stock_quantity هنا (يتكفّل بها hook الموديل).
```

#### الحجز والإطلاق

```
reserve(): قفل السطر → إن sellable ≥ الكمية → increment(reserved_quantity) → true، وإلا false (بلا استثناء)
release(): قفل السطر → reserved_quantity = max(0, reserved_quantity − الكمية) (بلا استثناء أبداً)
sellableOn(row) = max(0, available_quantity − reserved_quantity)
```

`defaultWarehouseId()` = أول مستودع بالـ id الأصغر — **وليس بالضرورة** المستودع الموسوم `is_primary`.

### 3.3 حركات المخزون — `StockMovement`

- **الأنواع**: `in` / `out` / `adjustment`.
- `movement_key`: مفتاح idempotency — العمود الذي يفحصه `InventoryService::move()` قبل أي كتابة.
- **Hook** عند الإنشاء: يزيد/ينقص `products.stock_quantity` (كاش إجمالي عبر الشركة) — هذا الحقل مسؤولية هذا الـ hook حصراً؛ `InventoryService` لا يكتبه مباشرة.
- **قيم `source` المستخدمة فعلياً**: `production`, `customer_register`, `assignment`, `wms`, `mobile_order`, `subscriber`, `rma`, `batch`, `serial_numbers`, `purchase_request`, `purchase_receipt`, `stock_count`, `sales`, `opening_stock`, `return`, `transfer`, `stock_import`.

`StockMovementController` هو نقطة الدخول اليدوية العامة (`store()`) — تفوّض مباشرة لـ `InventoryService::receive/issue/adjust` حسب `movement_type`، مع تمرير `key`/`reference`/`source`/`condition`/`unit_cost`.

### 3.4 تكلفة FIFO — `InventoryCostingService` وطبقات التكلفة

جدول خام `inventory_cost_layers` (ليس نموذج Eloquent): `product_id, warehouse_id, received_quantity, remaining_quantity, unit_cost (4 خانات عشرية), source, reference, stock_movement_id, received_at`.

| الدالة | القاعدة |
|---|---|
| `addLayer()` | يفتح طبقة جديدة `remaining = received = الكمية`. حتى تكلفة صفرية تفتح طبقة (وإلا لا يمكن تسعير تلك الوحدات لاحقاً عند الإخراج) |
| `consume()` | **يجب أن تُنفَّذ داخل معاملة المستدعي** (قفل صفوف). تسحب من الطبقات المفتوحة **الأقدم أولاً** (`received_at, id` تصاعدياً) — FIFO خالص، وتكسر التعادل بترتيب الإدخال. عند نقص الطبقات عن المطلوب (مخزون قديم غير مُطبَّق عليه FIFO): **تُسعَّر الفجوة بـ `products.cost_price`** كطبقة احتياطية (`layer_id=0`) |
| `returnLayer()` | تُعيد فتح طبقة **بنفس تكلفة الخروج الأصلية** (لا بسعر اليوم) — تُستخدم لعكس أثر تكلفة عملية بيع مُلغاة بدقة |
| `valueOnHand()` | `SUM(remaining_quantity × unit_cost)` — القيمة الدفترية الحقيقية للمخزون |

**بذر الرصيد الافتتاحي**: كل سطر `warehouse_inventory` بكمية > 0 وقت ترحيل هجرة إنشاء الطبقات حصل على طبقة افتتاحية واحدة بسعر `cost_price` الحالي، `source='opening_balance'` — لا يوجد تاريخ FIFO حقيقي قبل هذا التاريخ.

`InventoryService::move()` هو المستدعي الوحيد لـ `consume()`/`addLayer()` ضمن مسار الحركة المُراقَب.

### 3.5 توجيه/تخصيص طلبات البيع على المستودعات

هذا الموضوع مُفصَّل بالكامل في §5 (دورة حياة طلب البيع) لأنه يمتزج بعمق بالمنطق المالي (الفاتورة، القيد، الحجز). الخلاصة السريعة:

- **`sales_order_item_allocations`** (قديم): مستوى **بند-مستودع** — كم بالضبط من كل مستودع لكل بند. موثَّق بالكامل في `docs/warehouse-order-allocations.md`.
- **`sales_order_routings`** (جديد): مستوى **طلب-مستودع** — أي المستودعات مسموح أصلاً أن تُشارك في تزويد الطلب (قائمة سماح، بلا كمية).
- `OrderAllocationService::suggestAllocations()` يبقى محرّك اقتراح تلقائي بحت (جشع: الرئيسي أولاً ثم الأعلى رصيداً)، **لا يحجز شيئاً** — تفاصيل الخوارزمية في `docs/warehouse-order-allocations.md §4`.
- **تنظيف كود ميت**: النسخة القديمة الكاملة من `OrderAllocationService` (`allocateOrder`, `findBestWarehouse`, `canFulfillOrder`...) كانت مساراً موازياً معطَّلاً أصلاً (يستعلم عمود `available_stock` غير الموجود فيرمي خطأ SQL عند أول استدعاء)، وحُذفت بالكامل بدل إصلاحها. التخصيص/الحجز/الشحن/الترحيل ملكية حصرية الآن لـ `SalesOrderWorkflowService`.

### 3.6 فلتر مصادر الطلبية الميدانية والتزويد الاستباقي

موثَّقان بالتفصيل الكامل في `docs/field-branch-order-sourcing.md` (12 قسماً). ملخص القواعد الجوهرية:

- **F1–F8**: الطلب الميداني يُخدَم افتراضياً من مستودع البائع وحده؛ النقص يُعرَض على المستودع الرئيسي **بموافقة صريحة** (`supply_from_main`)؛ العجز الكامل يرفض الطلب كاملاً بلا تقليص تلقائي؛ المعاينة لا تحجز؛ البند المكمِّل بند مستقل يحمل تخصيصه الخاص.
- **R1–R7** (التزويد الاستباقي): يُدرَج الصنف عند `المتاح ≤ حد إعادة الطلب`؛ `target_level = max(reorder_point, متوسط البيع اليومي × مدة التوريد + مخزون الأمان)`؛ طلب واحد يغطي عدة أصناف؛ الرفض ذرّي (لا حجز جزئي).

### 3.7 دورة حياة طلب التزويد (نقل بين مستودعات الشركة)

موثَّقة بالتفصيل الكامل في `docs/replenishment-transfer-lifecycle.md`. ملخص القواعد الجوهرية (`ReplenishmentWorkflowService`، T1–T10):

- الطلب يحجز في المصدر فوراً ولا يحرّك شيئاً.
- **شحن (`delivery`)**: خروج عند الموافقة، دخول عند الاستلام (رِجلان منفصلتان زمنياً، والبضاعة "في الطريق" بينهما).
- **استلام من المستودع (`pickup`)**: لا حركة عند الموافقة، الرِجلان معاً عند الاستلام الفعلي.
- الاستلام الجزئي يُحرِّر حجز الفارق فوراً؛ الشحنة الناقصة تُسجَّل فروقاتها (`discrepancies`) دون ابتلاعها بصمت.
- كل رِجل حركة تحمل مفتاح idempotency فريد (`transfer:{id}:item:{itemId}:out|in`).

**`InventoryTransferController` (النسخة الإدارية غير الميدانية)**: كانت تحمل نسخة قواعد ثانية متباعِدة (استلام بلا كميات صريحة كان يعني "لا شيء" هنا و"كل ما شُحن" هناك)؛ أُعيد ربطها بالكامل لتفوِّض لنفس `ReplenishmentWorkflowService` — قاعدة واحدة تخدم الطرفين الآن. `store()` وحدها تبقى خاصة بهذا المتحكم (حجز أولي عبر `InventoryService::reserve`).

### 3.8 ملف جرد المخزون (تصدير/استيراد)

موثَّق بالتفصيل الكامل في `docs/inventory-stock-sheet.md`. القاعدة المحورية:

```
الكمية الإجمالية = السليمة + التالفة + المحتجزة
المتاح للبيع     = الكمية الإجمالية − المحجوز − التالفة − المحتجزة
```

**"المحجوز" لا يُستورد أبداً** — هو ناتج طلبات عملاء حقيقية، وملف قابل للكتابة فوقه يستطيع تحرير وعود قُطعت لعملاء. يُصدَّر للاطّلاع فقط ويُبلَّغ عنه عبر `ignored_columns` عند الاستيراد. الأعمدة المحسوبة (السليمة، المحجوز، المتاح للبيع) للقراءة فقط.

### 3.9 تخطيط الاحتياجات (MRP) — `MrpService`

يعمل **فقط** على تعيينات `ProductWarehouseAssignment` المضبوطة صراحة على `planning_method = 'mrp'` (تعيينات `rop` مستبعدة كلياً من هذا المحرّك).

```
currentStock         = available_stock (الصيغة الموحَّدة — كانت سابقاً تتجاهل الحجز وتُبالغ في العرض المتاح)
dailyDemand           = Σ(حركات الخروج آخر 30 يوماً) / 30
grossRequirements[يوم] = Σ(بنود طلبات مؤكَّدة بتاريخ تسليم = هذا اليوم) + dailyDemand  (تُضاف فوق الطلبات الفعلية، لا تُصافى معها)
scheduledReceipts[يوم] = أوامر شراء مفتوحة (approved/ordered) بتاريخ متوقَّع = هذا اليوم
                        + تحويلات مخزون قيد النقل (in_transit) بتاريخ وصول متوقَّع = هذا اليوم

لكل يوم من اليوم إلى نهاية الأفق:
    netRequirement = max(0, gross + safetyStock − projectedAvailable − scheduledReceipt)
    إن > صفر:
        orderQuantity = max(netRequirement, minStock)
        إن وُجد حد أقصى: orderQuantity = min(orderQuantity, maxStock − projectedAvailable)
        releaseDate = اليوم − مهلة التوريد
        إن releaseDate ≥ اليوم: سجّل أمراً مخطَّطاً (طلب/تحويل، مطلوب بحلول هذا اليوم)
        projectedAvailable += orderQuantity
    projectedAvailable = projectedAvailable + scheduledReceipt − gross
```

**توصيات بثلاث درجات**: `current_stock < safety_stock` → **عاجل** (طلب فوري)؛ `current_stock < min_stock_level` → **تحذير**؛ كل أمر مخطَّط من الحلقة أعلاه → **مجدوَل**. إن كانت `replenishment_method = warehouse_transfer` يبحث عن مستودع مصدر آخر يملك فائضاً (`findSourceWarehouse`) بدل التوصية بالشراء.

> **⚠️ فجوة تنفيذية**: `executeMrpRecommendations()` وما تحته (`createPurchaseOrder`, `createInventoryTransfer`) **دوال محشوّة (stubs)** — تُرجع بنية مصفوفة لكنها **لا تُنشئ فعلياً** أي سطر `PurchaseOrder` أو `InventoryTransfer`. توصيات MRP استشارية فقط اليوم، بلا مسار تلقائي حي لتحويلها لأمر فعلي.

### 3.10 المنتجات المركّبة (BOM/التجميع/التفكيك) — `CompositeProductService`

- `canAssembleProduct()`: لكل مكوّن مطلوب (`quantity_required × الكمية المطلوب تجميعها`)، تُقارَن بالمتاح (لمستودع محدَّد أو مجموعه عبر كل التعيينات).
- `getBestWarehouseForAssembly()`: تسجيل نقاط لكل مستودع نشط (المتاح لكل مكوّن + مكافأة قرب جغرافي)، بشرط تغطية **كل** المكوّنات هناك.
- `createAssemblyOrder()`: يُنشئ `product_assembly_orders` + سطر لكل مكوّن، **ويحجز** بزيادة `reserved_quantity` مباشرة (وليس عبر `InventoryService::reserve()` — يتجاوز قفل الصف الخاص ببوابة البيع). *(إصلاح حديث: توقف عن طرح `available_quantity` أيضاً عند الحجز — كان يُسقط الكمية مرتين ويكسر معادلة الدِلاء طوال بقاء الأمر مفتوحاً)*.
- `completeAssemblyOrder()`: يستهلك كل مكوّن (`quantity` و`available_quantity` معاً الآن)، يفكّ حجزه، وينشئ/يزيد رصيد المنتج الأب. **⚠️ لا يمر عبر `InventoryService`/`InventoryCostingService` إطلاقاً** — لا سطر `StockMovement`، لا استهلاك/فتح طبقة FIFO — فتنحرف `inventory_cost_layers` صامتة عن الكمية الحقيقية عند كل تجميع/تفكيك.
- `disassembleProduct()`: عكس التجميع، بنفس القيد (كتابة مباشرة على `WarehouseInventory`، بلا أثر محاسبي أو سجل حركة).
- `calculateCompositeProductCost()`: `Σ(تكلفة المكوّن × الكمية المطلوبة)` باستخدام `products.cost_price` — **منفصل عن تقييم FIFO الحقيقي**.

### 3.11 الانتقاء (Picking) — نظام واحد بعد إزالة المسار القديم

كان يوجد سابقاً نظامان حيّان يعملان على نفس جداول `picking_lists`/`picking_list_items` بدلالات مختلفة تماماً لتوقيت خروج المخزون: `App\Services\Inventory\PickingService` (تُستخدم من `PickingController` على مسارات `/picking/*`) كانت تُخرج المخزون **فوراً** عند `confirmPicking()` بالكتابة المباشرة على `WarehouseInventory`، متجاوزة `InventoryService` وتكلفة FIFO والقيد المحاسبي كلياً، ودون كتابة `StockMovement`.

**تم الحل**: تبيّن أن `/picking/*` لم تكن مستخدَمة من أي شاشة في الواجهة (`resources/js`) — الواجهة تستدعي `/picking-lists/*` فقط، التي تمر عبر `WmsController` و`App\Services\PickingService`. لذا حُذف `PickingController` و`App\Services\Inventory\PickingService` ومساراتهما بالكامل، وأصبح `App\Services\PickingService` (المستخدَم من `WmsController` وَ`SalesOrderWorkflowService`) المسار الوحيد: قائمة منفصلة لكل مستودع موجَّه إليه الطلب (`createPickingListsForPlan`)، والخصم الفعلي **مؤجَّل** إلى `SalesOrderWorkflowService::applyShipment` — `pickItem()` لا يلمس كميات المخزون إطلاقاً.

### 3.12 الشراء → استلام المخزون — `PurchaseReceiptController`

```
داخل معاملة واحدة:
1. إنشاء PurchaseReceipt + بنوده (total = quantity × unit_price لكل سطر).
2. تحديد المستودع: warehouse_id الصريح، وإلا المستودع الافتراضي.
3. لكل سطر: InventoryService::receive(..., key: 'purchase_receipt:{id}:item:{product_id}', source: 'purchase_receipt', unit_cost: سعر الوحدة)
   — مفتاح idempotency لكل (إيصال، سطر) يمنع الإدخال المزدوج عند إعادة حفظ الإيصال.
4. ترحيل محاسبي: ledger->postGoodsReceipt($receipt)  (تفصيل كامل في §4.3.6)
5. زيادة رصيد المورد الدائن (شراء بالآجل).
```

**قواعد حماية صارمة بعد الإدخال**:
- **`update()`**: بنود الإيصال **غير قابلة للتعديل بعد الاستلام** — أي محاولة تعديل `items` تُرفَض (422) وتُوجَّه لتسجيل تسوية مخزنية أو إيصال إرجاع بدلاً من ذلك. الحقول الوصفية فقط (ملاحظات، تاريخ) قابلة للتعديل.
- **`destroy()`**: **محظور دوماً** (422) — إيصال مُرحَّل لا يُحذَف (سيترك قيداً معلَّقاً ومخزوناً غير مُفسَّر).

### 3.13 الجرد الدوري (Cycle Count)

دورة الحياة: `pending → in_progress → completed`، مع `review()` و`applyAdjustment()`. `CycleCountItem::calculateVariance()`: `الفرق = المعدود − المتوقَّع`، `قيمة الفرق = |الفرق| × تكلفة الوحدة`.

> **⚠️ فجوة وظيفية جوهرية**: لا يوجد أي مسار كود يستدعي `InventoryService::adjust()` من نتيجة جرد دوري. `applyAdjustment()` على الموديل والمتحكم **يكتفيان بتبديل علم `requires_adjustment` وتسجيل من ولمَ**، ولا يُصحِّحان رصيد المستودع فعلياً. أي أن **الجرد الدوري يسجّل الفروقات ولا يطبّقها أبداً على المخزون الحقيقي** — على عكس تعديل الكمية اليدوي في `WmsController::updateAssignment` أو `ProductWarehouseAssignmentController::updateStockBalance` اللذين يمرّان فعلاً عبر `InventoryService`.

### 3.14 المرتجعات (RMA) → المخزون

`RmaController::receiveItems()` هي نقطة إعادة الإدخال الفعلية (مشروطة بحالة `approved` أو `received`):

```
لكل سطر: markAsReceived(الكمية) — إسناد لا إضافة (idempotent: الفارق = الجديد − المُستلَم سابقاً، لا مضاعفة عند إعادة الإرسال)

تخطيط الحالة الفيزيائية إلى الدلو (فقط إن الفارق ≠ صفر و الحالة ≠ "مفقود"):
    new (سليم)  → available   (مرتجع سليم يعود للبيع)
    damaged     → damaged     (تالف)
    used        → quarantined (قيد المعاينة — لا يُباع حتى الفحص)
    missing     → لا يُدخَل للمخزون إطلاقاً (لم يصل فعلياً)

الحجز عبر InventoryService::move() (بلا مفتاح idempotency صريح — الأمان يأتي من إسناد الكمية لا إضافتها)
```

بعد وصول أي كمية موجبة، تتقدم حالة الطلب لـ `received`. **`RmaController::store()`** يمنع تجاوز الكمية المُرجَعة سابقاً لكل بند طلب (حصة، وليست فحص مخزون).

### 3.15 أداة فحص التكامل — `inventory:check`

الأداة المرجعية الرسمية لسلامة الثلاثة سجلات (`warehouse_inventory`, `products.stock_quantity`, `stock_movements`):

| الفحص | القاعدة |
|---|---|
| `checkNegativeStock` | `quantity < 0` أو `available_quantity < 0` |
| `checkBucketConsistency` | `quantity ≠ available + damaged + quarantined` |
| `checkOverReservation` | `reserved_quantity > available_quantity` |
| `checkProductTotals` | `products.stock_quantity ≠ Σ(warehouse_inventory.quantity)` — قابل للإصلاح التلقائي (`--fix`، أحادي الاتجاه: صفوف المستودع هي الحقيقة المرجعية) |
| `checkOrphanedStock` | أزواج (منتج، مستودع) لها حركات مسجَّلة بلا سطر رصيد مطابق |

> لا يوجد إصلاح تلقائي لعدم اتساق الدلاء أو المخزون السالب أو فرط الحجز — `--fix` يمس `products.stock_quantity` فقط.

---

## 4. قواعد التسوية المالية

### 4.1 دليل الحسابات وهيكل الأستاذ

**`LedgerAccount`**: الحقول الجوهرية `code, name, type, balance, is_active, account_type, currency, opening_balance, is_system, parent_id, posting_role, warehouse_id`.

**`signedDelta(type, debit, credit)`** — المرجع الوحيد لأثر أي سطر قيد على الرصيد:

```
الحسابات "مدينة الطبيعة" (asset, expense):     الأثر = مدين − دائن
الحسابات "دائنة الطبيعة" (liability, equity, revenue): الأثر = دائن − مدين
```

تُستخدم نفس الدالة للتطبيق وللعكس (القيد المعاكس = نفي القيمة) — لا يتباعد المساران أبداً.

**`posting_role`** عمود **فريد** عبر دليل الحسابات (باستثناء حسابات المخزون الفرعية لكل مستودع التي تُحل بـ `warehouse_id` بدل الدور، لأن الدور مشترك بينها جميعاً).

**`JournalEntryHeader`**: `entry_number` (فريد، `JE-000123`)، **`posting_key`** (فريد — مفتاح منع الترحيل المزدوج)، `source_module`، `reversal_of_id`، `status` (`posted`/`reversed`، بالإضافة لحالات "غير مُرحَّل" في التقارير).

### 4.2 دليل الحسابات الفعلي (البذر التشغيلي)

| الكود | الاسم | النوع | `posting_role` |
|---|---|---|---|
| 1000 | الأصول (أب) | asset | — |
| 1001 | الصندوق | asset | `cash` |
| 1002 | البنك | asset | `bank` |
| 1003 | ذمم العملاء المدينة | asset | `accounts_receivable` |
| 1004 | سلف الموظفين | asset | `employee_advances` |
| 1005 | المخزون (أب) | asset | `inventory` (احتياطي fallback) |
| 1005-{id} | مخزون - {اسم المستودع} | asset | — (يُحدَّد بـ `warehouse_id`) |
| 1006 | بضاعة في الطريق | asset | `inventory_in_transit` |
| 2001 | ضريبة القيمة المضافة المستحقة | liability | `tax_payable` |
| 2002 | ذمم الموردين الدائنة | liability | `accounts_payable` |
| 3001 | رأس المال | equity | `capital` |
| 3002 | الأرباح المحتجزة | equity | `retained_earnings` |
| 4001 | إيرادات المبيعات | revenue | `sales_revenue` |
| 4002 | مردودات المبيعات | revenue | `sales_returns` |
| 4003 | خصومات المبيعات | revenue | `sales_discounts` |
| 4004 | إيرادات الشحن والخدمات | revenue | `additional_charges_revenue` |
| 5001 | تكلفة البضاعة المباعة | expense | `cogs` |
| 5002 | مصروف الشحن | expense | `shipping_expense` |
| 5003 | مصروف التغليف | expense | `packaging_expense` |
| 5004 | الرواتب والأجور | expense | `salaries_expense` |
| 5005 | العمولات | expense | `commissions_expense` |
| 5006 | مصروفات تشغيلية أخرى | expense | `other_expense` |

#### القاعدة المحورية: حساب مخزون مستقل لكل مستودع

كل مستودع له حساب `1005-{warehouse_id}` تحت الأب `1005`، **يُنشَأ تلقائياً عند أول حاجة إليه** (`inventoryAccountIdFor()` — lazy provisioning عبر `firstOrCreate` على الكود تحديداً، لمنع فتح الحساب مرتين عند طلبين متزامنين). الشرط المحاسبي الصارم:

> **الإدخال والإخراج يجب أن يلمسا الحساب نفسه.** البيع يُدين تكلفة البضاعة المباعة ويُقرن **حساب كل مستودع** بحصته (`postCostOfGoodsSoldBySource`)؛ الشراء يُدين **حساب المستودع المستلِم** (`postGoodsReceipt`).

كانت الحالة السابقة (قبل الإصلاح المُوثَّق في الكود) تُدين الشراء الحساب المجمَّع القديم بينما يُقرِن البيع حسابات المستودعات الفرعية — فتهبط أرصدة كل مستودع فقط ولا ترتفع أبداً (تذهب سالبة رغم امتلاء الرف)، ويتضخّم الحساب الأب. **الإصلاح للأمام فقط**: القيود القديمة على الحساب المجمَّع تبقى كما رُحِّلت، لا إعادة كتابة للتاريخ المحاسبي.

### 4.3 خدمة الترحيل المحاسبي — `LedgerPostingService`

**القواعد الثلاث المُعلنة في رأس الملف (السياسة العامة لكل ترحيل آلي):**

1. **متوازن أو لا شيء** — لا يُكتب القيد إلا إذا تساوى المدين والدائن (تفاوت مسموح `EPSILON = 0.005`)، وإلا `RuntimeException`.
2. **مرة واحدة بالضبط** — كل قيد يحمل `posting_key` مشتقاً من مستنده المسبِّب؛ إعادة إطلاق نفس الحدث تُرجع القيد الموجود بدل الترحيل المزدوج (idempotent).
3. **لا تُعاد كتابة التاريخ** — الإلغاء يُنتج قيداً عكسياً؛ الأصل يبقى كما هو.

الحسابات تُحل عبر `posting_role` لا أكواد صلبة — يمنع كسر الترحيل عند إعادة ترقيم الدليل.

#### 4.3.1 `postInvoice(Invoice)`

```
Dr  ذمم العملاء المدينة (accounts_receivable)     = total
    Cr  إيراد المبيعات (sales_revenue)                 = goods
    Cr  إيراد الشحن والخدمات (additional_charges_revenue) = charges
    Cr  ضريبة القيمة المضافة المستحقة (tax_payable)       = tax

حيث goods = total − tax − charges  (وليس subtotal — "إيراد البضاعة هو كل ما تبقى من الإجمالي غير مُفسَّر"، حتى يتوازن القيد على سجلات قديمة قد لا تتطابق فيها subtotal تماماً)
```
`total ≤ 0` → لا ترحيل. المفتاح: `invoice:{id}`.

#### 4.3.2 `postPayment(Payment)`

**الاسترداد يُخزَّن كدفعة سالبة (`amount < 0`)** — نفس الدالة تخدم الاتجاهين:

```
تحصيل عادي (amount > 0):  Dr نقد/بنك   Cr ذمم العملاء المدينة
استرداد (amount < 0):     Dr ذمم العملاء المدينة   Cr نقد/بنك
```
طريقة الدفع `cash` → حساب `cash`؛ أي طريقة أخرى (`card`, `bank_transfer`, `check`) → حساب `bank` (لا حساب مستقل للبطاقة/الشيك). المفتاح: `payment:{id}`.

#### 4.3.3 تكلفة البضاعة المباعة

- **`postCostOfGoodsSold()`** (صيغة مفردة): `Dr COGS  Cr مخزون (دور عام)`.
- **`postCostOfGoodsSoldBySource()`** (المستخدَمة فعلياً في `SalesOrderWorkflowService::applyShipment`):
```
Dr  تكلفة البضاعة المباعة  = الإجمالي
    Cr  مخزون - مستودع A  = حصته
    Cr  مخزون - مستودع B  = حصته
    ...
```
**يُرحَّل عند لحظة الشحن الفعلي**، لا عند تأكيد الطلب ولا عند إصدار الفاتورة — كي تتفق الدفاتر ومستودع البضاعة على متى توقفت البضاعة عن كوننا مالكيها. منتجات بلا تكلفة موجبة تُنتج ترحيلاً فارغاً (لا ضوضاء). المفتاح: `so_cogs:{order_id}`.

#### 4.3.4 التحويلات بين المستودعات

```
شحن التحويل:    Dr بضاعة في الطريق (1006)   Cr مخزون - المستودع المصدر
استلام التحويل: Dr مخزون - المستودع الوجهة   Cr بضاعة في الطريق (1006)
```
الاستلام يُرحَّل بما وصل فعلياً فقط — نقص التسليم يبقى الفارق "في الطريق" بدل الظهور بصمت في الوجهة.

#### 4.3.5 `inventoryAccountIdFor(warehouseId)` — منطق الحل

```
1. ابحث عن حساب asset مرتبط بـ warehouse_id هذا مباشرة — إن وُجد أرجعه.
2. وإلا: ابحث عن الأب 1005؛ إن وُجدا (المستودع والأب) أنشئ حساباً بالكود "1005-{id}"
   عبر firstOrCreate على الكود تحديداً (لمنع فتح مزدوج عند تزامن)، posting_role=null، عملة SAR.
3. fallback: إن غاب المستودع أو الأب 1005 من الدليل، عُد للحساب العام بدور "inventory"
   (تنصيب لم يُقسَّم بعد يستمر بالعمل كما كان قبل هذا الإصلاح).
```

#### 4.3.6 `postGoodsReceipt(receipt)`

```
Dr  مخزون - المستودع المستلِم   = Σ(الكمية × سعر الوحدة) لكل الأصناف
    Cr  ذمم الموردين الدائنة (accounts_payable)
```
شراء بالآجل يرفع المستحق (لا تسديد فوري). إن لم يُحدَّد `warehouse_id` على الإيصال → fallback للدور العام `inventory`. `total ≤ 0` → لا ترحيل. المفتاح: `goods_receipt:{id}`.

> هذا كان "النصف المفقود" تاريخياً من دورة المخزون: البيع كان يُدين COGS ويُقرِن `inventory`، لكن لا شيء كان يدين المخزون عند الاستلام — فيهبط الرصيد باستمرار دون قاع رغم امتلاء الرف فعلياً.

#### 4.3.7 `postCreditNote(CreditNote)`

```
Dr  مردودات المبيعات (sales_returns)  = total
    Cr  ذمم العملاء المدينة (accounts_receivable)
```
يخفّض الإيراد عبر **حساب مقابِل (contra account)**، لا بعكس فاتورة المبيعات مباشرة. المفتاح: `credit_note:{id}`.

#### 4.3.8 `postExpense(expense)`

المصروف يُثبَت وقت وقوعه (استحقاقاً، accrual)؛ خروج النقدية فعلياً يقرر الجانب الآخر:

```
مُسدَّد (paid/settled):     Dr مصروف   Cr نقد
غير مُسدَّد:                Dr مصروف   Cr ذمم الموردين الدائنة
```
تصنيف المصروف يحدد الحساب المدين (`shipping`→`shipping_expense`, `packaging`→`packaging_expense`, غيرها→`other_expense`). **قاعدة صريحة**: مصروف بحالة `rejected`/`cancelled` **لا يُرحَّل إطلاقاً** — "مصروف مرفوض لم يُنفَق فعلياً، وترحيله يخترع تكلفة". المفتاح: `expense:{id}`.

#### 4.3.9 `reverseFor(postingKey, date?)` — العكس

يبحث عن القيد بمفتاحه؛ إن غاب أو كان مُعكوساً مسبقاً → `null` (لا عكس مزدوج). ينشئ قيداً مرآة (يبدّل كل مدين/دائن) بمفتاح `{postingKey}:reversal`، ويحدّث الأصل لـ `status='reversed'` **فقط بعد** نجاح ترحيل العكس. يُستخدَم في: إلغاء طلب بيع (يعكس `so_cogs:{id}` و`invoice:{id}`)، حذف دفعة، إعادة إثبات فاتورة.

#### 4.3.10 القلب: `post()`

```
فحص التكرار مرتين: خارج المعاملة (سريع)، ثم داخل DB::transaction مع lockForUpdate() على posting_key
    (يمنع تسابق طلبين متزامنين على نفس المستند).
كل سطر يُحل حسابه إما مباشرة أو عبر دوره؛ حساب مفقود لدور مطلوب → استثناء فوري
    ("قيد ناقص أفضل من نصف قيد مكتوب").
الأسطر ≤ صفر تُستبعد؛ أقل من سطرين صالحين → لا ترحيل (لا معنى لقيد بسطر واحد).
تفاوت التوازن المسموح: 0.005، وإلا استثناء يُظهر الفارق الدقيق.
الترقيم: JE-000123 عبر lockForUpdate على آخر صف (يطابق ترقيم الشاشة اليدوية عمداً).
بعد كل سطر: increment('balance', signedDelta(...)) — الرصيد على الحساب تراكمي مباشر،
    وليس مُعاد الحساب من الحركات عند كل قراءة (على عكس بعض التقارير).
```

### 4.4 الفواتير — `Invoice`

| الحالات (`status`) | `pending, confirmed, processing, shipped, delivered, cancelled` — **مطابقة تماماً** لحالات طلب البيع، لا يوجد enum مستقل "مدفوعة/غير مدفوعة" |
|---|---|
| طرق الدفع على الفاتورة | `cash, card, transfer` — **لا تطابق** enum `Payment` (`cash, card, bank_transfer, check`)؛ يوجد تحويل صريح بينهما (`card→card`, `transfer→bank_transfer`, أي شيء آخر→`cash`) |

**القواعد الجوهرية**:
- **`isPaid()`**: `amountDue() ≤ 0.009` — وليس `status === 'delivered'` (كان الخلط السابق يربط "وصول البضاعة" بـ"وصول المال").
- **`amountDue()`**: `round(total − paid_amount, 2)`.
- **`markAsPaid()`**: تحدّث `paid_amount/due_amount/paid_at` فقط، **لا تلمس `status` إطلاقاً**.
- **`markAsDelivered()`**: عمداً **لا تضبط `paid_at`** — التسليم ليس دفعاً.
- **`cancel()`**: `status=cancelled`, `paid_at=null`.
- الإيراد المُعترَف به: كل الحالات ما عدا `pending`/`cancelled`.
- ترقيم الفاتورة: `INV-{Ymd}-{4 أحرف عشوائية}` — **غير مُقفَل بقاعدة بيانات** (خلافاً لـ `Payment`/`CreditNote`/`JournalEntryHeader` التي تستخدم `lockForUpdate` + عداد).
- `InvoiceItem::total_price` تُعاد حسابه دوماً عند الحفظ: `quantity × unit_price` — بصرف النظر عمّا أُرسل من العميل.

#### مسار الإنشاء اليدوي — `InvoiceController::store()`

```
1. subtotal = Σ(بنود) ؛ total = subtotal + tax − discount + إجمالي المصروفات (بحد أدنى صفر)
2. additional_charges = إجمالي المصروفات (يُخزَّن صراحة كي تتوازن subtotal + tax − discount = total)
3. تسوية رصيد العميل (settleCustomerAccount):
     - فاتورة غير مرتبطة بطلب بيع → customer->updateBalance(+total)
     - فاتورة محوَّلة من طلب بيع → لا رفع إضافي (الطلب رفع الرصيد مسبقاً عند إنشائه — تفادي تحصيل مزدوج)
     - إن وُجد مبلغ مدفوع فوراً → إنشاء Payment (completed) وخصمه من رصيد العميل
4. إخراج المخزون لكل سطر عبر InventoryService::issue(key: invoice:{id}:item:{item_id}, allow_negative: true)
     — نقص المخزون لا يمنع حفظ الفاتورة؛ يُبلَّغ عبر inventory_warnings بدل إفشال الطلب
5. ترحيل: postInvoice ثم postPayment (إن وُجدت) ثم postExpense لكل مصروف — غير قاتل عند الفشل
     (يُبلَّغ عبر accounting_warning دون إفشال إنشاء الفاتورة)
```

> **مساران منفصلان لإنشاء وتسوية الفاتورة**: (أ) `InvoiceController::store` اليدوي، و(ب) `SalesOrderWorkflowService::ensureInvoice/applyConfirmation` عند تأكيد طلب بيع — كلاهما يبني نفس بنية الأسطر المحاسبية بشيفرة منفصلة موثَّقة صراحة كتكرار مقصود ("يطابق `postInvoice`").

### 4.5 الدفعات — `Payment` و`PaymentRecorder`

طرق الدفع: `cash, card, bank_transfer, check`. الحالات: `pending, completed, failed, refunded`. **الدفعة السالبة (`amount < 0`) = استرداد** (لا enum منفصل).

#### `PaymentRecorder::record()` — المسار الموحَّد الوحيد للتحصيل على فاتورة

يمس أربعة سجلات يجب أن تتفق معاً: الدفعة، مبالغ الفاتورة، رصيد العميل، الأستاذ — سبب وجود هذه الخدمة هو منع تكرار هذا المنطق في كل نقطة استدعاء (وضياع أحدها بصمت).

```
المبلغ = round(amount, 2)؛ إن ≤ صفر → رفض.

قاعدة منع الدفع الزائد: إن (المبلغ − المتبقي على الفاتورة) > 0.009 → رفض
    ("الدفع الزائد يجعل الذمم سالبة، ويقول الدفتر إن العميل له مبلغ لم يدفعه أبداً")

داخل معاملة:
    إنشاء Payment برقم من max(id)+1 (لا count() — يتصادم بعد أي حذف أو تحصيلين متزامنين)
    paid_amount += المبلغ ؛ due_amount = max(0, total − paid)
    paid_at يُختم فقط عند التصفية الكاملة (paid + 0.009 ≥ total)
    status الفاتورة لا يُلمَس أبداً ("الحالة عمل الطلب لا الدفع")
    customer->updateBalance(−المبلغ)
    ledger->postPayment(payment)
```

`outstanding(invoice)`: `max(0, round(total − paid_amount, 2))` — من المبالغ المخزَّنة مباشرة، لا من `due_amount` (أكثر موثوقية).

#### `PaymentController`

- **`store()`**: مع `invoice_id` → تفويض كامل لـ `PaymentRecorder`. بلا فاتورة → "دفعة على الحساب" مباشرة (`customer->updateBalance(-amount)` ثم `postPayment`).
- **⚠️ `update()`**: **مسار قديم منفصل لا يمر عبر `PaymentRecorder`** — يعدّل `paid_amount`/`due_amount` مباشرة بالفرق، ويستدعي `markAsDelivered()` إن أصبح `due_amount ≤ 0` — **يتعارض مباشرة** مع القاعدة الموثَّقة صراحة في `Invoice::markAsDelivered()`/`PaymentRecorder` بعدم خلط "التسليم" بـ"الدفع"، ولا يُرحِّل فرق التعديل للأستاذ، ولا يُحدِّث رصيد العميل.
- **`destroy()`**: يعكس مبالغ الفاتورة يدوياً، يعيد للعميل مبلغ الدفعة، `ledger->reverseFor('payment:{id}')`، ثم **حذف صلب فعلي** لسجل `Payment` نفسه (رغم أن القيد المحاسبي يبقى معكوساً لا محذوفاً) — تعارض طفيف بين حذف السجل التشغيلي والاحتفاظ بالسجل المحاسبي.

### 4.6 إشعارات الدائن ومعالجة المرتجعات مالياً

#### `CreditNote` — عقد الاستهلاك الثلاثي

إشعار الدائن يُستهلَك بأي مزيج من ثلاث طرق، كل منها مُتتبَّعة بشكل منفصل لضمان قابلية التدقيق:

| الحقل | المعنى |
|---|---|
| `applied_to_invoice` | يُخصَم من مستحق فاتورة |
| `refunded_amount` | يُدفع نقداً/تحويلاً/شيكاً |
| `store_credit_amount` | يبقى كرصيد على حساب العميل |

`settled_amount = مجموع الثلاثة`. `open_amount = max(0, total − settled_amount)`. الحالات: `issued → partially_applied/applied`, أو `cancelled`.

**`syncStatus()`** — تُعاد الحالة من المبالغ الفعلية دوماً (لا تُضبَط يدوياً):
```
settled ≤ 0.009           → issued
settled ≥ total − 0.009   → applied
غير ذلك                   → partially_applied
cancelled لا تتغيّر أبداً عبر هذه الدالة
```

`CreditNoteController` **قراءة وإلغاء فقط** — لا إنشاء/تعديل يدوي (الإشعارات تصدر فقط عبر تدفق التسوية). الإلغاء مرفوض إن `settled_amount > 0.009` (منع كسر تسوية مُعتمَد عليها).

#### `RmaSettlementService` — التسوية المالية الكاملة للمرتجع

قرار البند (`resolution`) يحدد الأثر المالي:

| Resolution | الأثر |
|---|---|
| `refund` | مال يعود (نقد/رصيد متجر) |
| `discard` | مال يعود؛ البضاعة تُشطَب ولا تعود للمخزون |
| `exchange` | بضاعة بديلة، تُخصَم كائتمان من البند المُرجَع |
| `repair` | لا أثر مالي — يُصلَح ويعود للعميل |

**`refundableAmount()`**: يُصفّى على `[refund, discard]` فقط — لأن `RmaRequest::getTotalRefundAmountAttribute()` الخام تجمع **كل** البنود بما فيها `exchange`، وهو ما كان يمنح استبدالاً مجانياً واسترداداً نقدياً كاملاً لنفس الصنف معاً.

**`settleRefund(request, refundAmount, refundMethod, userId)`**:
```
1. يُصدر إشعار دائن أولاً بقيمة refundAmount كاملة.
2. يُطبَّق أولاً على المتبقي المستحق على الفاتورة: appliedToInvoice = min(due, remaining)
   ("مرتجع على فاتورة غير مدفوعة يجب أن يخفّضها، لا أن يدفع نقداً لعميل لم يدفع بعد")
   — يُخصَم من due_amount فقط، لا يمس total ولا paid_amount.
3. الباقي (remaining) حسب طريقة الاسترداد:
   store_credit: customer->updateBalance(−remaining) — رصيد فقط، بلا حركة نقدية.
   أي طريقة أخرى: Payment(amount = −remaining, status=refunded, رقم REF-{rma_number}).
       رصيد العميل لا يُلمَس هنا عمداً ("العميل دفع واسترد نقداً، فصافي وضعه معنا لم يتغيّر؛
       تعديل الرصيد أيضاً يمنحه ائتماناً مضاعفاً").
       لكن invoice.paid_amount يُخفَّض (استرداد نقدي على فاتورة مدفوعة يجب أن يُنقِص التحصيل المسجَّل)؛
       due_amount يبقى كما هو (الإشعار الدائن يغطي الفارق).
4. تحديث الإشعار الدائن + syncStatus().
5. ترحيل: postCreditNote دوماً، postPayment فقط إن وُجدت دفعة استرداد — غير قاتل عند الفشل.
```

**`createReplacementOrder()`** لبنود `exchange`: كل بند يحمل السعر الحقيقي (لا صفراً كما في نسخة سابقة)، والائتمان من البضاعة المُرجَعة يُطبَّق **كخصم** على سطر البديل، بحد أقصى قيمة البديل نفسه. ائتمان زائد (استبدال بصنف أرخص) يُعاد للعميل كرصيد. الطلب البديل يمر لاحقاً عبر دورة حياة `SalesOrder` العادية عند تأكيده (لا ترحيل مباشر هنا).

#### `RmaController` — دورة الحياة الكاملة

الحالات: `pending, approved, received, rejected, completed, cancelled`. **`RmaItem::calculateRefundAmount()`**:
```
refund = originalPrice × multiplier × quantity_requested
multiplier:  new=1.0 (كامل)، used=0.7 (70%)، damaged=0.5 (50%)، missing=0.0، افتراضي=0.5
```

### 4.7 تعدد العملات — نظام جديد بالكامل (غير مُلتزَم بعد)

**الفلسفة المُعلَنة صراحة في رأس `CurrencyService`**:

> كل شيء مالي في هذا النظام يُخزَّن ويُرحَّل بـ**عملة الأساس فقط**. التحويل هنا **للعرض فقط** — لا يُغيّر أبداً ما يُحمَّل على العميل، ولا محتوى الفاتورة، ولا ما يصل للدفتر.

السبب: التحويل عند الإدخال يعني أن الدفاتر تحمل مبالغ بأسعار صرف لم يسجّلها أحد، وسعر صرف يتحرك بين الطلب والدفع يترك ذمة لا تُسوَّى بفارق لا يفسّره أي قيد. حتى تُبنى محاسبة عملات أجنبية صحيحة (قيود بعملة أجنبية + أرباح/خسائر صرف محقَّقة)، التحويل يبقى في جانب العرض فقط.

| النموذج | القواعد |
|---|---|
| `Currency` | `code` يُفرَض دوماً `UPPER(trim())`؛ `rateAt(moment)` تُرجع أحدث سعر بدأ سريانه بحلول تلك اللحظة |
| `CurrencyRate` | **سجل append-only بشكل صريح** — سعر خاطئ يُصحَّح بإدخال سعر جديد، لا بتعديل القديم (تعديل سعر سابق يعيد كتابة التاريخ بصمت). `rate` بدقة `decimal:8`. الصيغة: "وحدات هذه العملة مقابل وحدة واحدة من عملة الأساس" |

**دوال `CurrencyService`**:
- `rateFor(code, moment)`: `'1'` لعملة الأساس؛ **`null` صراحة** إن غاب السعر — لا تخترع `1.0` أبداً ("اختراع 1.0 كان سيُسعِّر الدولار بسعر الريال بصمت").
- `round(amount, currency)`: تقريب لأقرب `rounding_step` (فئة نقدية متداولة) إن وُجد، وإلا لعدد الخانات العشرية المُعرَّف.
- `recordRate()`: يرفض سعراً ≤ صفر؛ يرفض تغيير سعر عملة الأساس عن `1.0`.
- **`setBase(Currency)`**: **لا يُعيد التعبير عن المبالغ المخزَّنة إطلاقاً** — تبديل الأساس حدث محاسبي، وتُصرِّح الواجهة به مباشرة: "الأرصدة المخزّنة لم تُحوَّل — أعد إدخال أسعار الصرف مقابل العملة الجديدة".

**حالة ما قبل هذا النظام** (موثَّقة في رأس migration الإنشاء): 9 جداول مالية تحمل عمود `currency` و3 تحمل `exchange_rate`، لكن كل كاتب كان يضع الحرفي `'SAR'` و`1.0000`، واختيار العملة كان قائمة من 5 عناصر مُبرمَجة يدوياً في كنترولرين، وتطبيق Flutter كان يطبع "ر.س" بلا شرط.

**⚠️ حالة التكامل الحالية**: **لا يوجد استخدام فعلي لـ `CurrencyService` داخل `LedgerPostingService`, `InvoiceController`, `PaymentController`, أو `SalesOrderWorkflowService`** حتى الآن. الحقول القديمة (`invoices.currency`, `invoices.exchange_rate`, إلخ) لا تزال تُملأ حرفياً بـ`SAR`/`1.0000`. نظام العملات الجديد **طبقة عرض عامة قائمة بذاتها** (`GET /api/v1/currencies`) وشاشة إدارة، غير متكامل بعد مع مسار الترحيل الفعلي للمستندات المالية.

### 4.8 رصيد العميل / المحفظة

**`Customer::updateBalance($amount)`**: `increment('balance', amount)` — بسيطة، **موجب = رفع الدين على العميل، سالب = تخفيضه**. لا قفل تفاؤلي داخلها (يعتمد على المُستدعي لضمان الاتساق ضمن معاملة).

**نداءات `updateBalance` الموزَّعة عبر النظام** (الرصيد تراكمي، **وليس** محسوباً من الفواتير/الدفعات عند كل قراءة):

| المصدر | الأثر |
|---|---|
| `InvoiceController::settleCustomerAccount` | `+total` (فاتورة غير مرتبطة بطلب)، `-paidAmount` (دفعة مصاحبة) |
| `SalesOrderWorkflowService::applyConfirmation` | `+order.total` فقط إن لم تكن الفاتورة موجودة أصلاً |
| `SalesOrderWorkflowService::applyCancellation` | `-invoice.total` عند إلغاء طلب له فاتورة |
| `SalesOrderWorkflowService::restateInvoice` | `+الفرق` عند إعادة إثبات فاتورة |
| `PaymentRecorder::record` | `-المبلغ` عند كل تحصيل |
| `RmaSettlementService::settleRefund` | `-الباقي` عند استرداد كـ`store_credit` |
| `RmaSettlementService::createReplacementOrder` | `-فائض الائتمان` |
| `PurchaseRequestController` | `+subtotal` (طلبات شراء داخلية) |

> **⚠️ لا آلية مصالحة مركزية** تتحقق أن `Customer.balance` يطابق مجموع الفواتير المستحقة ناقص الإشعارات المفتوحة — الرصيد تراكمي بحت عبر استدعاءات موزّعة، عرضة للانجراف إن فشل أي استدعاء بصمت (كما في `PaymentController::update` أعلاه الذي لا يلمس الرصيد).

**`WalletTransaction` / `FlutterWalletController`** — نظام **منفصل تماماً** عن `Customer.balance`؛ يخص `User` (لا `Customer`)، **لا ترحيل محاسبي فيه إطلاقاً** (لا استدعاء لـ `LedgerPostingService`)، ولا صلة مكتشفة بينه وبين رصيد العميل أو الدفتر — يبدو نظاماً موازياً غير مندمج محاسبياً مع بقية المنظومة.

### 4.9 `ErpUpgradeService` — مسار حجز تراثي محتمل

3 دوال، **بلا أي استدعاء لـ `LedgerPostingService`**:

- **`allocateLandedCost()`**: يوزّع تكاليف الشحن/الجمارك/التأمين على أصناف إيصال شراء (بالقيمة أو بالكمية)، يرفع `unit_price` على السطر مباشرة — يرفع تلقائياً ما يُرحَّل لاحقاً كمخزون مدين عبر `postGoodsReceipt`.
- **`reserveInventory()`** — **الدالة المعدَّلة في هذا الفرع**: الشرط أصبح `available_stock < الكمية` بدل `quantity − reserved_quantity < الكمية` — يمنع الآن حجز وحدات تالفة أو محجورة قيد المعاينة (متوافق مع التوحيد في §3.1).
- **`releaseInventory()`**: يفكّ الحجز، وإن `isShipped=true` يخصم أيضاً من الكمية الفعلية.

لا استدعاء فعلي مكتشَف لهاتين الدالتين من أي متحكم حالي — يستحق تأكيداً إضافياً إن كان لا يزال مساراً نشطاً أم كوداً ميتاً، منفصل بأي حال عن `InventoryService` و`SalesOrderWorkflowService::reserveAll/releaseAll` الموثَّقين كالمسار الرسمي.

### 4.10 التقارير المالية — طبقتان متمايزتان

| | `FinancialAnalyticsService` (أقدم) | `AccountingReportController` (أحدث وأدق) |
|---|---|---|
| مصدر الأرقام | استعلامات مباشرة مبسَّطة (`SalesOrder.total` بحالة `delivered`، `PurchaseOrder.total` بحالة `received`) | `journal_entry_lines` عبر `posting_role`، مجمَّعة SQL |
| فصل COGS عن المصروفات | لا — كل الأسطر المدينة معاً | نعم — `COST_OF_SALES_ROLES` منفصلة |
| **⚠️ عيب مكتشَف** | `getAccountsReceivable/Aging` تعتمد `status != 'paid'`، لكن `paid` **ليست حالة موجودة فعلياً** في enum الفاتورة — الشرط لن يُطابق أي فاتورة كـ"مدفوعة" أبداً | — |
| فحوصات الاتساق الذاتي | لا | نعم (`systemHealth()`) |

**أبرز نقاط `AccountingReportController`**:
- **`trialBalance()`**: ميزان مراجعة لفترة، مع `is_balanced` والفارق والقيود غير المتوازنة.
- **`incomeStatement()`**: مُدرَّج (إجمالي إيراد − مردودات/خصومات = صافي إيراد − COGS = مجمل ربح − مصروفات = صافي دخل)، مقارَن تلقائياً بالفترة المكافئة السابقة، مع تحذيرات مضمَّنة (تكلفة > 0 وإيراد ≈ 0 → خطأ ترحيل؛ إيراد > 0 وتكلفة ≈ 0 → تحذير هامش مبالغ فيه؛ رصيد مردودات باتجاه معكوس → تحذير).
- **`balanceSheet()`**: تحمل "نتيجة الفترة الجارية" (إيراد − مصروف غير المُقفَل) كسطر حقوق ملكية منفصل — وإلا لا تتحقق معادلة `A = L + E` أبداً قبل إقفال الفترة.
- **`systemHealth()`** (فحص شامل بلا كتابة): فواتير/دفعات غير مرحَّلة، طلبات شُحنت بلا قيد تكلفة، طلبات مؤكَّدة بلا فاتورة، أصناف بلا سعر تكلفة، أرصدة مخزون سالبة، قيود غير متوازنة (على مستوى القيد وعلى مستوى الدفتر كله)، مع اقتراح أمر Artisan لكل نوع خلل.

**`JournalEntryController`** (قيود يدوية): يفرض `min:2` أسطر، كل سطر مدين أو دائن فقط (ليس كلاهما)، توازن **بلا** تفاوت EPSILON (أدق من الترحيل الآلي). **⚠️ `update()`/`destroy()` يعدّلان/يحذفان القيد اليدوي مباشرة** (يعكسان الأثر القديم على الرصيد ثم يطبّقان الجديد) — **يخالف فلسفة `LedgerPostingService::reverseFor`** (لا إعادة كتابة تاريخ). تصميمان متعايشان بقواعد مختلفة: الآلي لا يُعدَّل أبداً ويُعكَس فقط، واليدوي قابل للتعديل/الحذف المباشر.

**`LedgerAccountController`**: يمنع تغيير `type` الحساب إن كانت له قيود مُرحَّلة فعلاً (سيُشوِّه الأرصدة لأن `signedDelta` يعتمد النوع الحالي).

### 4.11 ملخص القواعد المحاسبية العابرة (Cross-Cutting)

1. الترحيل **الآلي** دائماً idempotent وغير قابل للحذف — عكس بقيد، لا حذف ولا تعديل.
2. الترحيل **اليدوي** (شاشة اليومية) قابل للتعديل/الحذف مباشرة — مسار موازٍ بقواعد مختلفة تماماً.
3. **التكلفة تُرحَّل عند الشحن الفعلي**، لا عند التأكيد أو الفاتورة. **الإيراد يُرحَّل عند التأكيد/الفاتورة**.
4. كل حساب مخزون تابع لمستودع محدَّد (`1005-{id}`)؛ حساب مشترك واحد فقط للتنصيبات القديمة غير المهيَّأة، بآلية إنشاء عند الطلب (lazy).
5. رصيد العميل تراكمي عبر نداءات موزَّعة — عرضة للانجراف إن فشل أو نُسي أحدها.
6. **الاسترداد النقدي لا يُعدِّل رصيد العميل؛ رصيد المتجر (store credit) يُعدِّله** — تمييز متعمَّد.
7. نظام العملات الجديد طبقة عرض بحتة حالياً، غير متكامل بعد مع ترحيل المستندات الفعلي.

---

## 5. دورة حياة طلب البيع

هذه الحلقة هي نقطة التقاء المخزون والمالية — `SalesOrderWorkflowService` هي المالكة الحصرية لكل من: التخصيص، الحجز، الشحن، الفوترة، والترحيل.

### 5.1 آلة الحالات

| من | إلى (المسموح) |
|---|---|
| `pending` | `confirmed`, `cancelled` |
| `confirmed` | `processing`, `cancelled` |
| `processing` | `shipped`, `cancelled` |
| `shipped` | `delivered`, `cancelled` |
| `delivered` | نهائية |
| `cancelled` | نهائية |

`SHIPPED_STAGES = [shipped, delivered]` (البضاعة غادرت فعلاً). `RESERVED_STAGES = [confirmed, processing]` (محجوزة، لم تغادر بعد). لا يوجد `delivered → cancelled` — الإرجاع بعد التسليم يمر عبر RMA (§4.6)، لا عبر إلغاء الطلب.

**`transitionTo()`** العامة: قفل صف الطلب (`lockForUpdate`) → فحص الانتقال ضمن الجدول → الإلغاء يتطلب سبباً (`note`) إلزامياً → تنفيذ الأثر عبر `match` → تسجيل `SalesOrderStatusHistory` (append-only) → إرجاع `{changed, from, status, effects}`.

### 5.2 التأكيد (`pending → confirmed`) — أهم مرحلة

```
1. assertHasItems() — رفض طلب بلا أصناف.
2. planSourcing($order) — تحديد مصدر (مصادر) البضاعة (تفصيل §5.7).
3. fulfillment_warehouse_id = المستودع "المالك".
4. assertCanCover() — تحقق نهائي من التغطية ضد كل مصدر فعلي.
5. reserveAll() — حجز الكمية في كل مصدر (لا إخراج بعد).
6. إن لم تكن الفاتورة موجودة مسبقاً: customer->updateBalance(+total).
7. ensureInvoice() — فاتورة واحدة فقط لكل طلب.
8. ledger->postInvoice(invoice).
9. confirmed_at = now().
10. إنشاء قائمة تجهيز واحدة لكل مستودع مُوجَّه إليه (picking->createPickingListsForPlan) —
    فشلها لا يُلغي التأكيد (البيع مُلتزَم به فعلاً)، يُسجَّل كتحذير في الأثر فقط.
```

### 5.3 المعالجة والشحن

`processing`: لا أثر مالي، فقط يُبقي الحجز قائماً.

`applyShipment()` (`processing → shipped`):
```
1. يتطلب fulfillment_warehouse_id موجوداً.
2. إنهاء قائمة التجهيز أولاً — تناقض بين المُجهَّز والمُراد شحنه يوقف الانتقال بالكامل
   قبل خروج أي بضاعة.
3. لكل بند: shipmentSourcesFor() يحدد من أي مصدر(ات) يخرج كل جزء من الكمية.
4. لكل مصدر: inventory->shipReserved() — يستهلك المحجوز فعلياً، بمفتاح
   SO-{orderId}-{productId}-W{sourceWarehouseId} (مُفتّح لكل مصدر لا لكل منتج فقط).
5. COGS تُؤخذ من التكلفة الفعلية للحركة (FIFO الحقيقية)، لا من cost_price الحالي —
   دقة الهامش عند اختلاف تكلفة الدفعات.
6. ledger->postCostOfGoodsSoldBySource() — مقسَّماً حسب المستودع المصدر.
7. shipped_at = now(). مزامنة حالة الفاتورة (لا تُنزَّل فاتورة سبق تسديدها).
```

### 5.4 التسليم

`applyDelivery()`: `delivered_at = now()`. التسوية **اختيارية** (`options['settle']`) — يمكن التسليم دون تحصيل. إن طُلبت: يُحسب المتبقي عبر `PaymentRecorder::outstanding`، والمبلغ الافتراضي هو الباقي كاملاً إلا إن أُرسل مبلغ جزئي صراحة؛ `payments->record()` — نفس مسار شاشة المدفوعات، فتتزامن الفاتورة/الرصيد/الدفتر بغض النظر عن "الباب" الذي جاء منه التحصيل.

### 5.5 الإلغاء

يتفرّع حسب حالة المصدر:

| من | الأثر |
|---|---|
| `SHIPPED_STAGES` (شُحن فعلاً) | تُعاد البضاعة **لكل مستودع بحسب مصدره الحقيقي** (لا لمستودع الطلب المالك وحده). `ledger->reverseFor('so_cogs:{id}')`. |
| `RESERVED_STAGES` (لم يُشحن بعد) | `releaseAll()` — إطلاق الحجز فقط (البضاعة لم تغادر الرف). |
| بغض النظر عن المرحلة | إلغاء أي قائمة تجهيز معلَّقة؛ إن وُجدت فاتورة: `reverseFor('invoice:{id}')`، `status=cancelled`, `paid_at=null`، وخصم `-invoice.total` من رصيد العميل. |

### 5.6 تغيير نوع التنفيذ (`changeFulfillmentType`)

آلية مستقلة (شحن/استلام من فرع/توصيل): مرفوضة بعد الشحن؛ **الاستلام الشخصي (`pickup`) يرفض طلباً مُقسَّماً على أكثر من مستودع** (يجب توحيد المصدر أولاً). عند تغيير المستودع مع وجود حجز قائم: يحجز في الجديد أولاً، **ثم** يُطلق من القديم (ترتيب متعمَّد لتفادي خسارة وحدات لطلب آخر لو فشل الجديد). تغيّر تكلفة الشحن يُعيد حساب الإجمالي ويُعيد إثبات الفاتورة (عكس القديم + ترحيل بمفتاح جديد مطبوع بالزمن).

### 5.7 خوارزمية التوجيه — `planSourcing()`

**تغيير جوهري**: كان التوجيه يحدث سابقاً عند *إنشاء* الطلب (افتراضياً أول مستودع نشط بالـ ID الأصغر، بصرف النظر عن وجود مخزون فعلي)؛ أُزيل هذا تماماً — الآن يبقى `fulfillment_warehouse_id` فارغاً حتى **التأكيد**، حيث يقرر التوفر الحقيقي.

```
1. البنود التي تحمل allocations مسبقة (يدوية أو من الحقل) تُترك كما هي — تُخصَم فقط
   من "الرصيد الحر" لتفادي وعد نفس الوحدات مرتين.
2. دفتر "متاح حر" مؤقت في الذاكرة يتتبع كل (مستودع، منتج) مع خصم ما هو مخصَّص سلفاً.
3. إن كانت كل البنود لها خطة مسبقة → بناء النتيجة مباشرة دون توزيع إضافي.
4. المرشَّحون (sourceCandidates) — §5.8. إن فارغون → رسالة خطأ حسب نوع التنفيذ.
5. تجميع الاحتياج حسب المنتج (سطرا نفس المنتج يستهلكان نفس الرف، يُقاسان معاً لا منفردين).
6. المحاولة الأولى: مستودع واحد يغطي كل الاحتياج دفعة واحدة (أفضل مرشَّح بالترتيب).
   إن وُجد ولم يكن مالك الطلب: الطلب يبقى مملوكاً لنفس مستودعه (لا يُنقَل)، لكن يُنشأ
   allocation يوجّه الكمية كاملة لهذا المستودع (نقل الطلب كان سيُخرجه من قائمة الفرع
   الذي باعه فعلياً).
7. إن لم يوجد مستودع واحد كافٍ:
   - pickup → رفض فوري (لا تقسيم لطلب استلام شخصي).
   - غير ذلك → splitAcross(): توزيع جشع لكل بند على المرشَّحين بالترتيب؛ عجز الشبكة
     كاملة (بعد المرور على كل المرشَّحين) → تراجع كامل + رسالة نقص شاملة واحدة.
```

### 5.8 ترتيب المرشَّحين — `sourceCandidates()`

```
المستودعات النشطة فقط.
إن كان الطلب "موجَّهاً" فعلياً (سجلات في sales_order_routings): تُقيَّد القائمة على هذه فقط
    — توجيه متعمَّد يتفوّق على الخطة التلقائية.
pickup: تُقيَّد على مستودعات نوع "فرع" فقط (أو المستودع الصريح المُختار حتى لو لم يكن فرعاً).

الترتيب (تصاعدياً حسب الأولوية):
    1. هل هذا مستودع الطلب المالك حالياً؟
    2. هل هذا مستودع الموظف المسؤول عن الطلب؟
    3. هل هو is_primary (المستودع الرئيسي)؟
    4. id تصاعدياً (استقرار).
```

### 5.9 فحوص التغطية — `assertCanCover()` و`sellableFor()`

يُقاس **كل بند مقابل مصادره الفعلية المخطَّطة** (عبر `shipmentSourcesFor`)، لا مقابل مستودع الطلب المالك وحده — بند "مُغذّى" من المستودع الرئيسي (تكملة الفرع، §3.6) محجوز ويُشحن من هناك فعلياً، وقياسه ضد الفرع كان يُظهر نقصاً وهمياً.

```
sellableFor = max(0, available_quantity − reserved_quantity)  + تعويض حجز الطلب نفسه
    (إن كان الطلب يحمل حجزاً حالياً في هذا (منتج، مستودع)، يُضاف حجزه الخاص للمتاح —
     كي لا "يُقصِّر" حجزه هو نفسه على إعادة الفحص/التوجيه لاحقاً)
```

### 5.10 `shipmentSourcesFor()` — قلب التوحيد

يُستخدَم في كل من: الحجز، الفحص، الشحن، الإلغاء، خطط الانتقاء، خطة المصادر.

```
لا allocations → كل الكمية من مستودع الطلب المالك (fallback).
توجد allocations → تُجمَّع حسب المستودع؛ إن كان المجموع أقل من كمية البند (خطة جزئية/قديمة)،
    الفارق يُضاف تلقائياً لمستودع الطلب المالك — لا تشحن خطة جزئية كمية أقل مما يُحاسَب
    عليها العميل أبداً.
```

### 5.11 شاشة "مصدر البضاعة" — `sourcingPlan()`/`saveSourcingPlan()`

عرض تفصيلي لكل بند وكل مستودع مرشَّح (`available` عبر `sellableFor`، `allocated` من الخطة الحالية). `editable` طالما لم يُشحن الطلب.

```
saveSourcingPlan($plan):
    رفض إن غير قابل للتعديل.
    رفض إن مصدر بالخطة خارج التوجيهات المُختارة (إن وُجدت).
    إن محجوز حالياً: إطلاق كل الحجوزات أولاً (فحص الخطة الجديدة ضد التوفر الحقيقي).
    لكل بند: مجموع الكميات = كمية البند بالضبط، وإلا رفض؛ حذف الخطة القديمة وإنشاء الجديدة.
    إن محجوز سلفاً: إعادة الحجز على الخطة الجديدة — فشل أي مصدر → تراجع المعاملة كاملة
        (الخطة القديمة وحجزها يبقيان سليمين، لا حالة "نصف مُطبَّقة").
```

### 5.12 جدول `sales_order_routings` مقابل `sales_order_item_allocations`

مفهومان **متمايزان ومتكاملان**، وليسا نفس الشيء المتطوّر:

| | `sales_order_item_allocations` (قديم) | `sales_order_routings` (جديد) |
|---|---|---|
| المستوى | **بند-مستودع** | **طلب-مستودع** |
| يمثّل | كم بالضبط من كل مستودع لكل بند (مجموعه = كمية البند) | أي المستودعات مسموح لها أصلاً أن تُشارك (اختيار بلا كمية) |
| العلاقة | `SalesOrderItem::allocations()` | `SalesOrder::routings()` (pivot) |
| فارغ يعني | لا خطة صريحة؛ يُشحن كاملاً من المستودع المالك | لا توجيه محدَّد؛ **كل** مستودع نشط مرشَّح (السلوك القديم) |

`selectedRoutingIds()`: `null` = لا قيود (الكل متاح)؛ وإلا IDs المُختارة **زائد** المستودع المالك تلقائياً (بند بلا تخصيص صريح يذهب افتراضياً هناك، فاستبعاده من "المسموح" ينتج خطة تتجاهلها الشحنة لاحقاً).

**إذن**: `routings` = قائمة سماح (whitelist) اختيارية؛ `allocations` = التوزيع الفعلي المُقاس داخل تلك القائمة. الأولى جديدة تماماً واختيارية (طلبات قديمة تستمر بالعمل بلا تغيير)، والثانية موجودة منذ الوثيقة القديمة وتخدم نفس الغرض التشغيلي (حجز/شحن/تكلفة لكل مصدر فعلي).

**`saveRoutings()` — القواعد**: يرفض قائمة فارغة؛ يتحقق نشاط كل المستودعات المختارة؛ `pickup` يرفض أكثر من مستودع واحد؛ **يمنع إزالة مستودع لا يزال يحمل تخصيصاً فعلياً بكمية موجبة** (منع حذف صامت لحجز حقيقي)؛ إن كان المستودع المالك الحالي خارج الاختيار الجديد، تُنقَل الملكية لأول عنصر مُختار.

### 5.13 رسائل الخطأ العربية المرجعية

| الحالة | الرسالة |
|---|---|
| لا فرع/مستودع نشط عند التخطيط | `لا يوجد فرع نشط يمكن للعميل الاستلام منه. غيّر نوع التنفيذ إلى شحن.` / `لا يوجد مستودع نشط يمكن توجيه الطلب إليه.` |
| pickup يحتاج تقسيماً | `لا يوجد فرع واحد يغطي كامل الطلب، ولا يمكن تقسيم طلب استلام من الفرع على أكثر من مستودع...` |
| عجز الشبكة كاملة | `الرصيد غير كافٍ في أي مستودع: {منتج} (ناقص X من أصل Y في كل المستودعات)…` |
| فشل التغطية النهائي | `الرصيد غير كافٍ: {منتج} في مستودع "{اسم}" (مطلوب X، متاح Y)…` |
| فشل حجز فعلي (سباق تزامن) | `تعذّر حجز X من "{منتج}" في مستودع {اسم} — الكمية المتاحة تغيّرت.` |
| طلب مُقسَّم → pickup | `الطلب مقسّم على N مستودعات، ولا يمكن استلامه من الفرع من عدة أماكن. وحّد مصدر البضاعة أولاً.` |
| إلغاء بلا سبب | `يرجى ذكر سبب الإلغاء — يُحفظ في سجل مراحل الطلب.` |
| تعديل توجيه/مصدر بعد الشحن | `لا يمكن تغيير... بعد شحنه — ما خرج من المستودعات واقعة لا تُعدَّل.` |

---

## 6. فجوات ومشاكل معروفة تستحق المتابعة

جدول موحَّد لكل ما رصده هذا البحث كتضارب منطقي أو فجوة تنفيذية في الكود الحالي — لأغراض التخطيط المستقبلي، لا كأخطاء "يجب إصلاحها فوراً" بالضرورة:

| # | الموضوع | الوصف | الموقع |
|---|---|---|---|
| 1 | `cost_basis` مزدوج الاستخدام | عمود enum (`FIFO/FEFO/LIFO`) يُكتَب فيه أحياناً سعر تكلفة رقمي؛ "إجمالي قيمة المخزون" في لوحة WMS مبني على ضرب الكمية بهذا العمود — النتيجة غير موثوقة | `WmsController::updateAssignment/dashboard` |
| 2 | نظاما حجز منفصلان لا يتصلان | `InventoryService::reserve()` (يكتب `warehouse_inventory.reserved_quantity`، وهو ما تفحصه بوابة البيع) مقابل `InventoryAllocationService::allocateFIFO/FEFO/LIFO` (يكتب `product_batches.quantity_reserved`) — لا يقرأ أحدهما الآخر | `EnhancedInventoryController` |
| 3 | نظاما انتقاء متوازيان | أحدهما يخصم المخزون فوراً ويتجاوز FIFO والتدقيق، والآخر يؤجّل الخصم لحظة الشحن — كلاهما حيّ على نفس الجداول | `Services/Inventory/PickingService` مقابل `Services/PickingService` |
| 4 | المنتجات المركّبة تتجاوز طبقة التكلفة | التجميع/التفكيك يكتب `WarehouseInventory` مباشرة بلا `StockMovement` ولا استهلاك/فتح طبقة FIFO | `CompositeProductService` |
| 5 | الجرد الدوري لا يُطبَّق فعلياً | يسجّل الفروقات (`variance`) لكن لا مسار كود يستدعي `InventoryService::adjust()` منه | `CycleCount::applyAdjustment` |
| 6 | MRP استشاري فقط | `executeMrpRecommendations()` لا تُنشئ فعلياً أمر شراء أو تحويل | `MrpService` |
| 7 | `PaymentController::update()` يتجاوز المسار المحاسبي الموحَّد | لا يمر عبر `PaymentRecorder`، لا يُرحِّل الفرق للدفتر، لا يحدّث رصيد العميل، ويخلط "التسليم" بـ"الدفع" رغم توثيق العكس صراحة في مكان آخر | `PaymentController.php` |
| 8 | حذف سجل الدفعة مقابل الاحتفاظ بالقيد | `PaymentController::destroy()` يحذف `Payment` فعلياً بينما القيد المحاسبي يبقى معكوساً لا محذوفاً | `PaymentController::destroy` |
| 9 | القيود اليدوية تُعدَّل مباشرة | يخالف مبدأ "لا إعادة كتابة للتاريخ" المُطبَّق على الترحيل الآلي | `JournalEntryController::update/destroy` |
| 10 | `FinancialAnalyticsService` يقيس حالة فاتورة غير موجودة | يفحص `status != 'paid'` بينما `paid` ليست قيمة enum فعلية على `Invoice` | `FinancialAnalyticsService::getAccountsReceivable/Aging` |
| 11 | نظام العملات غير متكامل مع الترحيل الفعلي | طبقة عرض قائمة بذاتها، لا يزال كل مستند مالي يُخزَّن بعملة الأساس الحرفية | `CurrencyService` مقابل `LedgerPostingService/InvoiceController` |
| 12 | `WalletTransaction` منفصل محاسبياً | لا ترحيل، لا صلة برصيد العميل أو الدفتر | `FlutterWalletController` |
| 13 | تحكّمات `Flutter*` غير موصولة بالمسارات | خط عمل موازٍ لتطبيق تسوّق مستهلك، بلا نقاط API فعلية مسجَّلة | `routes/api.php` |
| 14 | `ErpUpgradeService::reserveInventory/releaseInventory` مسار محتمل الموت | لا استدعاء حي مكتشَف، منفصل عن مسار الحجز الرسمي | `ErpUpgradeService.php` |
| 15 | لا رصيد افتراضي إلزامي لحد الائتمان | `Customer.remaining_credit` حقل معروض، لا فرض فعلي مكتشَف يمنع تجاوزه عند الترحيل | `Customer.php` |

---

## 7. ملحق: خريطة الملفات المرجعية

### المخزون

| الملف | الدور |
|---|---|
| `app/Models/WarehouseInventory.php` | النموذج المحوري (الدلاء الثلاثة + المحجوز) |
| `app/Services/Inventory/InventoryService.php` | مسار الكتابة المركزي الوحيد |
| `app/Models/StockMovement.php` | سجل التدقيق |
| `app/Services/Inventory/InventoryCostingService.php` | تكلفة FIFO الحقيقية |
| `app/Services/OrderAllocationService.php` | اقتراح توزيع تلقائي (بلا حجز) |
| `app/Services/InventoryAllocationService.php` | حجز دفعات/أرقام تسلسلية (منفصل — انظر §6.2) |
| `app/Services/Inventory/MrpService.php` | تخطيط الاحتياجات |
| `app/Services/Inventory/CompositeProductService.php` | المنتجات المركّبة/BOM |
| `app/Services/Inventory/PickingService.php` / `app/Services/PickingService.php` | الانتقاء (نظامان — §6.3) |
| `app/Services/Inventory/ReplenishmentWorkflowService.php` | نقل بين المستودعات (موثَّق في `docs/replenishment-transfer-lifecycle.md`) |
| `app/Services/Field/BranchOrderSourcingService.php` | تكملة الفرع من الرئيسي (موثَّق في `docs/field-branch-order-sourcing.md`) |
| `app/Console/Commands/InventoryCheck.php` | أداة فحص التكامل |

### المالية

| الملف | الدور |
|---|---|
| `app/Services/Accounting/LedgerPostingService.php` | خدمة الترحيل الوحيدة |
| `app/Models/LedgerAccount.php` / `JournalEntryHeader.php` / `JournalEntryLine.php` | دليل الحسابات والأستاذ |
| `app/Models/Invoice.php` / `InvoiceItem.php` | الفواتير |
| `app/Models/Payment.php` / `app/Services/Sales/PaymentRecorder.php` | الدفعات |
| `app/Models/CreditNote.php` / `app/Services/RmaSettlementService.php` | إشعارات الدائن وتسوية المرتجعات |
| `app/Models/Currency.php` / `CurrencyRate.php` / `app/Services/CurrencyService.php` | تعدد العملات (جديد) |
| `app/Http/Controllers/Api/AccountingReportController.php` | التقارير المحاسبية الدقيقة |
| `app/Services/FinancialAnalyticsService.php` | تحليلات مالية (طبقة أقدم) |

### المبيعات

| الملف | الدور |
|---|---|
| `app/Services/Sales/SalesOrderWorkflowService.php` | مالكة دورة حياة طلب البيع بالكامل |
| `app/Models/SalesOrder.php` / `SalesOrderItem.php` / `SalesOrderItemAllocation.php` | نموذج الطلب |
| `database/migrations/2026_08_11_090000_create_sales_order_routings_table.php` | جدول التوجيه الجديد |

### وثائق تفصيلية سابقة (مرجع تكميلي)

- `docs/warehouse-order-allocations.md` — توزيع طلبات سلة الموظف على المستودعات، خوارزمية الاقتراح التلقائي بالتفصيل
- `docs/field-branch-order-sourcing.md` — فلتر مصادر الطلبية الميدانية والتزويد الاستباقي
- `docs/replenishment-transfer-lifecycle.md` — دورة حياة طلب التزويد الكاملة (شحن/استلام)
- `docs/inventory-stock-sheet.md` — ملف جرد المخزون (تصدير/استيراد)
