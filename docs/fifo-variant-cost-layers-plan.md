# خطة: طبقات كلفة FIFO على مستوى المتغيّر

**الحالة:** منفّذة في 2026-11-17 (migration + الكود + الاختبارات + إسناد 77 طبقة من 96).
**التاريخ:** 2026-11-17

## المشكلة

`inventory_cost_layers` مفتاحها (المنتج، المستودع) فقط. عند صرف متغيّر، يسحب
`InventoryCostingService::consume()` أقدم طبقة للمنتج أياً كان متغيّرها.

مثال حقيقي: «غوما ساندويش اماراتي» له متغيّر «حبة واحدة» (كلفة 3.15) ومتغيّر «علبة»
(كلفة 63). بيع حبة بسعر 3.5 سحب كلفة 33.075 من طبقة العلبة، فظهر البند خاسراً 59.15،
بينما ستُصرف العلبة لاحقاً بكلفة الحبة فتظهر بربح وهمي.

- إجمالي الكلفة صحيح على مستوى المنتج (الدفاتر ومطابقة FIFO سليمة).
- ربحية كل متغيّر مشوّهة: 9 بنود حالياً (فحص `fifo_variant_mixing` في erd.html#logic).
- 61 منتجاً لها متغيّرات بكلف متباينة (أعلى من الأدنى بأكثر من 50%) معرّضة للمشكلة.

## الوضع الحالي (من الكود)

| المكوّن | المستوى | ملاحظة |
|---|---|---|
| `warehouse_inventory` | منتج + مستودع | العمود `product_variant_id` موجود لكنه فارغ في كل الصفوف (0 من 560) |
| `product_variants.stock_quantity` | متغيّر | عدّاد يُحدَّث عبر `ProductVariant::adjustStockCount()` |
| `stock_movements` | منتج | لا يوجد عمود متغيّر |
| `inventory_cost_layers` | منتج + مستودع | 972 طبقة، `stock_movement_id` فارغ في كلها |
| `InventoryService::issue/receive/adjust/transfer` | تستقبل `?int $productVariantId` | لكنها لا تمرّره إلى `consume()` و `addLayer()` |

## التغييرات المقترحة

### 1. Migration (إضافة فقط، قابلة للعكس)
- `inventory_cost_layers.product_variant_id` nullable + FK إلى `product_variants` (`nullOnDelete`).
- فهرس `(product_id, warehouse_id, product_variant_id, remaining_quantity)`.
- `stock_movements.product_variant_id` nullable (للتتبع فقط).

### 2. `InventoryCostingService`
- `addLayer(..., array $options)`: يقبل `$options['product_variant_id']`.
- `consume(int $productId, int $warehouseId, int $quantity, ?string $method = null, ?int $variantId = null)`:
  1. طبقات المتغيّر نفسه أولاً (بالترتيب المعتمد FIFO/LIFO/FEFO).
  2. ثم الطبقات القديمة بلا متغيّر (`product_variant_id IS NULL`) — مخزون ما قبل الترحيل.
  3. **لا** تُسحب طبقات متغيّر آخر أبداً (هذا هو الخلل الحالي).
  4. البديل الاحتياطي: `product_variants.cost_price` ثم `products.cost_price` (بدل كلفة المنتج فقط حالياً).
- `consumeWeightedAverage` و `resolveCostMethod` و `returnLayer` و `recalculateWeightedAverageForWarehouse`:
  نفس التقسيم حسب المتغيّر. المتوسط المرجّح يُحسب لكل متغيّر على حدة.
- `valueOnHand()`: بلا تغيير (مجموع الطبقات).
- عند `$variantId === null` يبقى السلوك الحالي تماماً (توافق خلفي).

### 3. `InventoryService`
- تمرير `$productVariantId` من `issue/receive/adjust/transfer` إلى `consume()`/`addLayer()`
  وإلى `stock_movements.product_variant_id`.

### 4. المستدعون (تمرير المتغيّر حيث يُعرف)
| الملف | المسار |
|---|---|
| `Services/Sales/GoodsIssueService.php` | صرف أوامر البيع والفواتير المباشرة |
| `Http/Controllers/Api/InvoiceController.php` | خطوط الفاتورة المباشرة (`product_variant_id` متوفر) |
| استلام المشتريات (`PurchaseReceipt…`) | `purchase_receipt_items.product_variant_id` |
| `Services/Purchasing/PurchaseReturnService.php` | مرتجعات المشتريات |
| `Http/Controllers/Api/RmaController.php::receiveItems` | `$variantId` محسوب أصلاً |
| `Http/Controllers/Api/InventoryTransferController.php` | `inventory_transfer_items.product_variant_id` |
| `Models/CycleCount.php` | تسويات الجرد |
| `Services/Inventory/ReplenishmentWorkflowService.php`، `FlutterOrderController`، `FieldReplenishmentController` | تمرير المتغيّر إن وُجد، وإلا null |

### 5. أمر إسناد الطبقات الحالية: `inventory:assign-layer-variants {--dry-run}`
للطبقات المفتوحة فقط (`remaining_quantity > 0`، حالياً 457 طبقة):
1. `source = purchase_receipt`: مطابقة `reference` مع `purchase_receipts.receipt_number` ثم
   `purchase_receipt_items` بنفس المنتج. إن وُجد متغيّر واحد → يُسند. إن وُجد أكثر (169 حالة)
   → المطابقة بالكمية و`unit_price` = `unit_cost`، وإلا تبقى null.
   (301 طبقة استلام قابلة للمطابقة مباشرة.)
2. `opening_stock` وغيرها: إسناد فقط إذا طابقت `unit_cost` كلفة متغيّر واحد بالضبط.
3. ما لا يمكن إسناده يبقى null ويُستهلك بعد طبقات المتغيّر (لا خسارة في الدقة عن الوضع الحالي).
4. يطبع تقريراً: مُسند / غامض / متروك، لكل منتج.

لا تعديل على الطبقات المستهلكة ولا على قيود كلفة المبيعات السابقة (فترات مرحّلة).

### 6. الاختبارات (`tests/Feature`)
بإضافة إلى `InventoryCostMethodTest` و `CostTrackingPropagationTest`:
- متغيّران بكلفتين مختلفتين: صرف (أ) يستهلك طبقات (أ) فقط.
- طبقة قديمة بلا متغيّر تُستهلك بعد نفاد طبقات المتغيّر وقبل البديل الاحتياطي.
- عدم وجود طبقات → البديل `variant.cost_price` ثم `product.cost_price`.
- المتوسط المرجّح لكل متغيّر.
- المرتجع (RMA) يعيد الطبقة لنفس المتغيّر وبتاريخها الأصلي.
- التحويل بين المستودعات يحمل المتغيّر.
- صرف بلا متغيّر (`null`) يطابق السلوك الحالي حرفياً (اختبار توافق).
- تشغيل كامل `php artisan test` قبل وبعد.

### 7. التشخيص
- `scripts/erd/logic.php`: فحص `fifo_layers` يبقى على مستوى المنتج (مطابقة مع `warehouse_inventory`)،
  ويُضاف فحص الطبقات المفتوحة بلا متغيّر لمنتجات لها متغيّرات (يجب أن يتناقص).
- `fifo_variant_mixing` يجب ألا تظهر فيه بنود جديدة بعد تاريخ النشر.

## خطوات النشر
1. نسخة احتياطية: `php artisan db:backup`.
2. نشر الكود + `php artisan migrate --force`.
3. `php artisan inventory:assign-layer-variants --dry-run` → مراجعة التقرير.
4. التنفيذ الفعلي، ثم `php artisan db:integrity-check` و `scripts/erd/build.sh`.
5. مراقبة أسبوع: `fifo_variant_mixing` لا يزيد.

## المخاطر
| الخطر | المعالجة |
|---|---|
| متغيّرات هي فعلياً نفس البضاعة بأسماء مختلفة | السحب من الطبقات بلا متغيّر ثم البديل يغطيها؛ لا رفض للصرف |
| مستدعٍ لم يُحدَّث يمرر null | يعمل بالسلوك الحالي (توافق خلفي) |
| إسناد خاطئ في أمر الإسناد | يُسند فقط عند تطابق وحيد؛ التشغيل التجريبي أولاً؛ الأمر قابل للعكس (إعادة null) |
| أداء | فهرس مركّب جديد؛ عدد الطبقات صغير (972) |

## التقدير
- الكود + Migration: نصف يوم.
- أمر الإسناد: ساعتان.
- الاختبارات: نصف يوم.
- النشر والتحقق: ساعة.
