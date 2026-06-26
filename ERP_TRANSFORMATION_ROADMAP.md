# خارطة طريق تحويل نظام ERP لشركة تجارية (توزيع/بيع بالتجزئة)

## نظرة عامة على المشروع

**الهدف:** تحويل نظام ERP الحالي من نظام محاسبي وإداري إلى منصة تشغيلية متكاملة وقابلة للتوسع تركز على إدارة التوزيع وسلسلة التوريد.

**النظام الحالي:**
- Laravel 12 + Vue.js 3 + Element Plus
- Livewire للمكونات التفاعلية
- Tailwind CSS للتصميم
- Pest للاختبار
- Laravel Sanctum للمصادقة

---

## المرحلة 1: تحليل الفجوة والتخطيط (أسبوع 1-2)

### 1.1 تقييم النظام الحالي
- ✅ تم تحليل النماذج الموجودة (Product, Warehouse, StockMovement, SalesOrder, Customer, Supplier)
- ✅ تم مراجعة واجهات API الموجودة
- ✅ تم تحديد البنية التحتية الحالية

### 1.2 تحديد الأولويات
**المستوى 1 - حرج (الأسبوع 3-8):**
- إدارة المخزون المتقدمة
- إدارة الطلبات والمبيعات المحسنة
- واجهات API حديثة

**المستوى 2 - مهم (الأسبوع 9-14):**
- نظام إدارة المستودعات (WMS)
- CRM وإدارة الموردين
- التقارير والتحليلات

**المستوى 3 - تحسيني (الأسبوع 15-20):**
- التكامل مع منصات التجارة الإلكترونية
- التنبؤ بالطلب بالذكاء الاصطناعي
- تحسينات UX/UI

---

## المرحلة 2: إدارة المخزون المتقدمة (الأسبوع 3-6)

### 2.1 الرؤية اللحظية متعددة المواقع
**المتطلبات:**
- رؤية فورية للمخزون عبر جميع المواقع
- نقل المخزون بين المواقع
- إعادة التوازن الآلي
- التسعير والتكلفة حسب الموقع

**التنفيذ:**

#### أ. تعزيز نموذج Warehouse
```php
// إضافة حقول جديدة
- location_type (warehouse, branch, distribution_center, 3pl)
- latitude, longitude
- capacity
- operating_hours
- is_primary
```

#### ب. تعزيز نموذج WarehouseInventory
```php
// إضافة حقول جديدة
- location_id (مواقع داخل المستودع)
- batch_number
- expiry_date
- serial_numbers (JSON)
- cost_basis (FIFO, FEFO, LIFO)
- last_counted_at
- count_variance
```

#### ج. إنشاء جدول InventoryTransfers
```php
Schema::create('inventory_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_warehouse_id')->constrained();
    $table->foreignId('to_warehouse_id')->constrained();
    $table->enum('status', ['pending', 'in_transit', 'completed', 'cancelled']);
    $table->timestamp('requested_at');
    $table->timestamp('shipped_at')->nullable();
    $table->timestamp('received_at')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});
```

#### د. إنشاء جدول InventoryTransferItems
```php
Schema::create('inventory_transfer_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transfer_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->integer('quantity_requested');
    $table->integer('quantity_shipped')->default(0);
    $table->integer('quantity_received')->default(0);
    $table->string('batch_number')->nullable();
    $table->date('expiry_date')->nullable();
    $table->decimal('unit_cost', 10, 2);
    $table->timestamps();
});
```

#### هـ. API Endpoints
```
GET  /api/v1/inventory/locations - عرض جميع المواقع
GET  /api/v1/inventory/locations/{id}/stock - مخزون موقع محدد
POST /api/v1/inventory/transfers - إنشاء طلب نقل
GET  /api/v1/inventory/transfers - عرض طلبات النقل
PUT  /api/v1/inventory/transfers/{id}/ship - شحن النقل
PUT  /api/v1/inventory/transfers/{id}/receive - استلام النقل
POST /api/v1/inventory/auto-rebalance - إعادة التوازن الآلي
```

### 2.2 إدارة الأصناف المعقدة
**المتطلبات:**
- خيارات المنتجات (الحجم، اللون، المادة)
- أنظمة التتبع (أرقام تسلسلية، أرقام دفعات، تواريخ صلاحية)
- تطبيق FIFO/FEFO تلقائياً

**التنفيذ:**

#### أ. تعزيز نموذج ProductVariant
```php
// إضافة حقول جديدة
- attributes (JSON) - {color: "Red", size: "M", material: "Cotton"}
- barcode
- sku
- weight
- dimensions (JSON)
- tracking_type (none, batch, serial, both)
- is_active
```

#### ب. إنشاء جدول ProductBatches
```php
Schema::create('product_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->foreignId('warehouse_id')->constrained();
    $table->string('batch_number')->unique();
    $table->date('manufacturing_date')->nullable();
    $table->date('expiry_date');
    $table->integer('quantity');
    $table->integer('quantity_reserved')->default(0);
    $table->decimal('unit_cost', 10, 2);
    $table->timestamps();
});
```

#### ج. إنشاء جدول ProductSerialNumbers
```php
Schema::create('product_serial_numbers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->foreignId('warehouse_id')->constrained();
    $table->string('serial_number')->unique();
    $table->enum('status', ['in_stock', 'reserved', 'sold', 'damaged', 'lost']);
    $table->foreignId('sale_order_id')->nullable()->constrained();
    $table->timestamp('sold_at')->nullable();
    $table->timestamps();
});
```

#### د. Service Class لـ FIFO/FEFO
```php
class InventoryAllocationService
{
    // FIFO - First In First Out
    public function allocateFIFO($productId, $quantity, $warehouseId)
    {
        // تخصيص من أقدم الدفعات
    }
    
    // FEFO - First Expired First Out
    public function allocateFEFO($productId, $quantity, $warehouseId)
    {
        // تخصيص من الدفعات الأقرب للانتهاء
    }
}
```

### 2.3 نقاط إعادة الطلب الديناميكية
**المتطلبات:**
- حساب ديناميكي بناءً على سرعة المبيعات
- المهل الزمنية
- متطلبات المخزون الاحتياطي
- تنبيهات آلية

**التنفيذ:**

#### أ. تعزيز نموذج WarehouseInventory
```php
// إضافة حقول جديدة
- reorder_point (محسوب)
- safety_stock (محسوب)
- lead_time_days
- average_daily_sales
- last_reorder_at
- auto_reorder_enabled
```

#### ب. إنشاء Job لحساب نقاط إعادة الطلب
```php
class CalculateReorderPoints implements ShouldQueue
{
    public function handle()
    {
        // حساب متوسط المبيعات اليومية
        // حساب نقطة إعادة الطلب = (متوسط المبيعات × المهل الزمنية) + المخزون الاحتياطي
        // تحديث السجلات
    }
}
```

#### ج. إنشاء جدول ReorderAlerts
```php
Schema::create('reorder_alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('warehouse_id')->constrained();
    $table->integer('current_quantity');
    $table->integer('reorder_point');
    $table->integer('suggested_order_quantity');
    $table->enum('severity', ['low', 'medium', 'critical']);
    $table->enum('status', ['pending', 'ordered', 'resolved']);
    $table->timestamp('alerted_at');
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
```

### 2.4 تتبع التكاليف الفعلية (Landed Cost)
**المتطلبات:**
- حساب تلقائي يشمل: سعر الشراء، الشحن، التأمين، الرسوم الجمركية
- توزيع التكاليف على الأصناف

**التنفيذ:**

#### أ. تعزيز نموذج LandedCost (موجود بالفعل)
```php
// إضافة حقول جديدة
- purchase_receipt_id
- allocation_method (value, weight, quantity, manual)
- is_allocated
- allocated_at
```

#### ب. Service Class لتوزيع التكاليف
```php
class LandedCostService
{
    public function allocateLandedCost($purchaseReceiptId, $allocationMethod)
    {
        // توزيع التكاليف على عناصر الإيصال
        // تحديث تكلفة الوحدة لكل صنف
    }
}
```

---

## المرحلة 3: إدارة الطلبات والمبيعات المحسنة (الأسبوع 7-10)

### 3.1 دورة المبيعات الكاملة
**المتطلبات:**
- أتمتة من عرض السعر إلى الفوترة
- دعم الأسعار المخصصة
- العقود المختلفة (دفع آجل، عاجل)

**التنفيذ:**

#### أ. تعزيز نموذج SalesOrder
```php
// إضافة حقول جديدة
- channel (web, pos, field_sales, marketplace)
- channel_reference_id
- pricing_type (standard, contract, custom)
- contract_id
- payment_terms (immediate, net_30, net_60, custom)
- due_date
- discount_type (percentage, fixed)
- discount_amount
- tax_exempt
- customer_po_number
- shipping_method
- tracking_number
```

#### ب. إنشاء جدول SalesContracts
```php
Schema::create('sales_contracts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->string('contract_number')->unique();
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('payment_terms', ['immediate', 'net_30', 'net_60', 'custom']);
    $table->decimal('credit_limit', 12, 2);
    $table->json('pricing_rules'); // قواعد التسعير الخاصة
    $table->enum('status', ['active', 'expired', 'cancelled']);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### ج. تعزيز نموذج SalesOrderItem
```php
// إضافة حقول جديدة
- base_price
- discount_percentage
- discount_amount
- tax_rate
- tax_amount
- line_total
- reserved_quantity
- shipped_quantity
- backordered_quantity
```

### 3.2 تنسيق الطلبات متعددة القنوات
**المتطلبات:**
- دمج الطلبات من جميع القنوات
- توزيع ذكي على أفضل موقع للشحن
- مزامنة لحظية

**التنفيذ:**

#### أ. إنشاء جدول OrderChannels
```php
Schema::create('order_channels', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('code')->unique();
    $table->enum('type', ['web', 'pos', 'marketplace', 'field_sales', 'api']);
    $table->boolean('is_active')->default(true);
    $table->json('settings');
    $table->timestamps();
});
```

#### ب. Service Class للتوزيع الذكي
```php
class OrderFulfillmentService
{
    public function determineBestWarehouse($orderId)
    {
        // تحديد أفضل مستودع بناءً على:
        // - توفر المخزون
        // - المسافة للعميل
        // - تكلفة الشحن
        // - سرعة التوصيل
    }
}
```

### 3.3 إدارة المرتجعات (RMAs)
**المتطلبات:**
- أتمتة عملية RMAs
- التقييم، الإذن، الاستلام
- إعادة البيع أو التخلص

**التنفيذ:**

#### أ. تعزيز نموذج RmaRequest (موجود)
```php
// إضافة حقول جديدة
- sales_order_id
- reason_type (damaged, wrong_item, customer_preference, defect)
- condition_on_return
- resolution_type (refund, replacement, repair, discard)
- refund_amount
- restock_fee
- status (requested, approved, rejected, received, processing, completed)
- approved_by
- received_by
- received_at
```

#### ب. إنشاء جدول RmaItems
```php
Schema::create('rma_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('rma_request_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->integer('quantity');
    $table->string('reason');
    $table->enum('condition', ['new', 'used', 'damaged', 'defective']);
    $table->enum('resolution', ['refund', 'replace', 'repair', 'discard']);
    $table->timestamps();
});
```

### 3.4 التكامل مع التجارة الإلكترونية
**المتطلبات:**
- تكامل مع Shopify و BigCommerce
- مزامنة لحظية للمخزون والأسعار والطلبات

**التنفيذ:**

#### أ. تعزيز نموذج IntegrationSetting (موجود)
```php
// إضافة حقول جديدة
- platform (shopify, bigcommerce, woocommerce, custom)
- api_key
- api_secret
- store_url
- webhook_url
- sync_settings (JSON)
- last_sync_at
- sync_status
```

#### ب. إنشاء Service Class لكل منصة
```php
class ShopifyIntegrationService
{
    public function syncProducts()
    {
        // مزامنة المنتجات
    }
    
    public function syncInventory()
    {
        // مزامنة المخزون
    }
    
    public function syncOrders()
    {
        // مزامنة الطلبات
    }
    
    public function handleWebhook($type, $data)
    {
        // معالجة Webhooks
    }
}
```

#### ج. إنشاء Jobs للمزامنة
```php
class SyncShopifyProducts implements ShouldQueue
{
    public function handle()
    {
        // مزامنة دورية للمنتجات
    }
}

class SyncShopifyInventory implements ShouldQueue
{
    public function handle()
    {
        // مزامنة دورية للمخزون
    }
}
```

---

## المرحلة 4: نظام إدارة المستودعات (WMS) (الأسبوع 11-14)

### 4.1 أتمتة العمليات
**المتطلبات:**
- توجيه الاستلام، التخزين، التقاط، التغليف، الشحن
- دعم الباركود/RFID
- تقليل الأخطاء

**التنفيذ:**

#### أ. تعزيز نموذج WarehouseBin (موجود)
```php
// إضافة حقول جديدة
- zone (A, B, C, D)
- aisle
- shelf
- level
- position
- bin_type (storage, picking, receiving, shipping)
- capacity
- current_capacity
- barcode
- is_active
```

#### ب. إنشاء جدول WarehouseTasks
```php
Schema::create('warehouse_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warehouse_id')->constrained();
    $table->enum('type', ['receiving', 'putaway', 'picking', 'packing', 'shipping', 'counting']);
    $table->foreignId('reference_id')->nullable(); // يمكن أن يكون purchase_receipt, sales_order, إلخ
    $table->string('reference_type')->nullable();
    $table->enum('priority', ['low', 'normal', 'high', 'urgent']);
    $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled']);
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->timestamp('assigned_at')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### ج. إنشاء جدول WarehouseTaskItems
```php
Schema::create('warehouse_task_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warehouse_task_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->foreignId('from_bin_id')->nullable()->constrained('warehouse_bins');
    $table->foreignId('to_bin_id')->nullable()->constrained('warehouse_bins');
    $table->integer('quantity');
    $table->integer('quantity_completed')->default(0);
    $table->string('barcode')->nullable();
    $table->string('serial_number')->nullable();
    $table->enum('status', ['pending', 'in_progress', 'completed']);
    $table->timestamps();
});
```

#### د. إنشاء جدول WarehouseWorkers
```php
Schema::create('warehouse_workers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->foreignId('warehouse_id')->constrained();
    $table->enum('role', ['picker', 'packer', 'receiver', 'supervisor']);
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_active_at')->nullable();
    $table->timestamps();
});
```

### 4.2 تحسين المسارات
**المتطلبات:**
- توجيه عمال المستودع لأفضل مسار
- تقليل وقت التنقل

**التنفيذ:**

#### أ. Service Class لتحسين المسارات
```php
class PickingRouteOptimizer
{
    public function optimizeRoute($warehouseId, $taskItems)
    {
        // خوارزمية تحسين المسار:
        // - تجميع العناصر القريبة
        // - تقليل العودة للخلف
        // - مراعاة تخطيط المستودع
    }
}
```

#### ب. إنشاء جدول WarehouseLayout
```php
Schema::create('warehouse_layouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warehouse_id')->constrained();
    $table->json('zones'); // تخطيط المناطق
    $table->json('aisles'); // تخطيط الممرات
    $table->json('bins'); // مواقع الأرفف
    $table->timestamp('updated_at');
});
```

### 4.3 إدارة حالات المخزون
**المتطلبات:**
- إدارة المواقع في المستودع
- حالات المخزون (متاح، محجوز، تالف)
- جولات الجرد الدوري

**التنفيذ:**

#### أ. تعزيز نموذج WarehouseInventory
```php
// إضافة حقول جديدة
- bin_id
- available_quantity
- damaged_quantity
- quarantined_quantity
- last_cycle_count_at
- cycle_count_frequency_days
```

#### ب. إنشاء جدول CycleCounts
```php
Schema::create('cycle_counts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warehouse_id')->constrained();
    $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins');
    $table->enum('type', ['full', 'partial', 'abc']);
    $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled']);
    $table->timestamp('scheduled_at');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->foreignId('counted_by')->nullable()->constrained('users');
    $table->foreignId('verified_by')->nullable()->constrained('users');
    $table->decimal('variance_amount', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

#### ج. إنشاء جدول CycleCountItems
```php
Schema::create('cycle_count_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cycle_count_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('product_variant_id')->nullable()->constrained();
    $table->foreignId('bin_id')->nullable()->constrained('warehouse_bins');
    $table->integer('system_quantity');
    $table->integer('counted_quantity');
    $table->integer('variance');
    $table->enum('status', ['pending', 'counted', 'verified']);
    $table->timestamps();
});
```

---

## المرحلة 5: CRM وإدارة الموردين (الأسبوع 15-18)

### 5.1 منصة موحدة للعملاء
**المتطلبات:**
- تجميع كل بيانات العملاء
- سجل المشتريات والتفضيلات
- تاريخ الدعم

**التنفيذ:**

#### أ. تعزيز نموذج Customer
```php
// إضافة حقول جديدة
- customer_type (individual, business, wholesale)
- industry
- company_size
- lead_source
- lead_score
- customer_tier (bronze, silver, gold, platinum)
- loyalty_points
- total_lifetime_value
- average_order_value
- purchase_frequency
- last_order_at
- preferred_payment_method
- preferred_shipping_method
- tags (JSON)
- custom_fields (JSON)
```

#### ب. إنشاء جدول CustomerInteractions
```php
Schema::create('customer_interactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('user_id')->constrained(); // الموظف
    $table->enum('type', ['call', 'email', 'meeting', 'note', 'support_ticket']);
    $table->text('subject');
    $table->text('notes');
    $table->timestamp('interaction_date');
    $table->enum('outcome', ['positive', 'neutral', 'negative']);
    $table->boolean('follow_up_required')->default(false);
    $table->timestamp('follow_up_date')->nullable();
    $table->timestamps();
});
```

#### ج. إنشاء جدول CustomerPreferences
```php
Schema::create('customer_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained();
    $table->json('product_preferences'); // المنتجات المفضلة
    $table->json('category_preferences'); // التصنيفات المفضلة
    $table->json('communication_preferences'); // تفضيلات التواصل
    $table->json('delivery_preferences'); // تفضيلات التوصيل
    $table->timestamps();
});
```

### 5.2 إدارة الموردين
**المتطلبات:**
- تتبع أداء الموردين
- إدارة العقود
- أتمتة الشراء بناءً على التنبؤ
- تنبيهات التأخير

**التنفيذ:**

#### أ. تعزيز نموذج Supplier
```php
// إضافة حقول جديدة
- supplier_type (manufacturer, distributor, wholesaler, service)
- rating (1-5)
- performance_score (0-100)
- on_time_delivery_rate
- quality_rate
- response_time_hours
- payment_terms
- currency
- minimum_order_quantity
- contract_start_date
- contract_end_date
- is_preferred
- tags (JSON)
- certifications (JSON)
```

#### ب. إنشاء جدول SupplierContracts
```php
Schema::create('supplier_contracts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->constrained();
    $table->string('contract_number')->unique();
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('payment_terms', ['immediate', 'net_30', 'net_60', 'net_90']);
    $table->decimal('credit_limit', 12, 2);
    $table->json('pricing_schedule'); // جدول الأسعار
    $table->json('delivery_terms');
    $table->enum('status', ['active', 'expired', 'terminated']);
    $table->text('terms_and_conditions')->nullable();
    $table->timestamps();
});
```

#### ج. إنشاء جدول SupplierPerformance
```php
Schema::create('supplier_performance', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->constrained();
    $table->date('period_start');
    $table->date('period_end');
    $table->integer('total_orders');
    $table->integer('on_time_deliveries');
    $table->decimal('on_time_rate', 5, 2);
    $table->integer('quality_issues');
    $table->decimal('quality_rate', 5, 2);
    $table->decimal('average_response_time', 8, 2);
    $table->decimal('total_value', 12, 2);
    $table->decimal('overall_score', 5, 2);
    $table->timestamps();
});
```

#### د. إنشاء Service Class لأتمتة الشراء
```php
class PurchaseAutomationService
{
    public function generatePurchaseOrders()
    {
        // بناءً على نقاط إعادة الطلب
        // بناءً على التنبؤ بالطلب
        // مراعاة المهل الزمنية
    }
    
    public function checkSupplierPerformance()
    {
        // تقييم أداء الموردين دورياً
    }
}
```

### 5.3 التنبؤ بالطلب
**المتطلبات:**
- تحليلات تنبؤية بالذكاء الاصطناعي
- استخدام البيانات التاريخية
- مراعاة العوامل الخارجية

**التنفيذ:**

#### أ. إنشاء جدول DemandForecasts
```php
Schema::create('demand_forecasts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('warehouse_id')->nullable()->constrained();
    $table->date('forecast_date');
    $table->integer('period_days');
    $table->integer('forecasted_quantity');
    $table->integer('actual_quantity')->nullable();
    $table->decimal('accuracy', 5, 2)->nullable();
    $table->enum('algorithm', ['moving_average', 'exponential_smoothing', 'arima', 'prophet', 'lstm']);
    $table->json('parameters');
    $table->enum('confidence_level', ['low', 'medium', 'high']);
    $table->timestamp('generated_at');
    $table->timestamps();
});
```

#### ب. Service Class للتنبؤ
```php
class DemandForecastingService
{
    public function generateForecast($productId, $periodDays = 30)
    {
        // خوارزميات التنبؤ:
        // 1. المتوسط المتحرك
        // 2. التنعيد الأسي
        // 3. ARIMA
        // 4. Prophet (Facebook)
        // 5. LSTM (TensorFlow/PyTorch)
    }
    
    public function calculateAccuracy($forecastId)
    {
        // مقارنة التنبؤ بالواقع
    }
}
```

#### ج. إنشاء Job للتنبؤ الدوري
```php
class GenerateDemandForecasts implements ShouldQueue
{
    public function handle()
    {
        // تشغيل دوري للتنبؤ بالطلب
    }
}
```

---

## المرحلة 6: التقارير والتحليلات (الأسبوع 19-22)

### 6.1 لوحات معلومات تفاعلية
**المتطلبات:**
- لوحات مخصصة لكل دور
- مؤشرات الأداء الرئيسية
- تحديثات لحظية

**التنفيذ:**

#### أ. إنشاء Controller للوحات المعلوماتية
```php
class DashboardController extends Controller
{
    public function managerDashboard()
    {
        // KPIs للمدير:
        // - إجمالي المبيعات
        // - هامش الربح
        // - قيمة المخزون
        // - أداء الموردين
    }
    
    public function salesDashboard()
    {
        // KPIs لمشرف المبيعات:
        // - المبيعات حسب القناة
        // - أداء مندوبي المبيعات
        // - العملاء الجدد
    }
    
    public function purchasingDashboard()
    {
        // KPIs لمسؤول المشتريات:
        // - طلبات الشراء المعلقة
        // - تنبيهات إعادة الطلب
        // - أداء الموردين
    }
    
    public function warehouseDashboard()
    {
        // KPIs لمسؤول المستودع:
        // - دقة المخزون
        // - مهام الانتظار
        // - معدل دوران المخزون
    }
}
```

#### ب. إنشاء جدول DashboardConfigurations
```php
Schema::create('dashboard_configurations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('dashboard_name');
    $table->json('widgets'); // تكوين الويدجت
    $table->json('filters'); // الفلاتر الافتراضية
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

### 6.2 تقارير متقدمة
**المتطلبات:**
- إنشاء تقارير مخصصة
- تحليل ربحية القنوات والمنتجات والعملاء
- تصدير بتنسيقات متعددة

**التنفيذ:**

#### أ. إنشاء جدول ReportTemplates
```php
Schema::create('report_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->json('query_config'); // تكوين الاستعلام
    $table->json('columns'); // الأعمدة
    $table->json('filters'); // الفلاتر المتاحة
    $table->json('groupings'); // التجميعات
    $table->json('calculations'); // الحسابات
    $table->foreignId('created_by')->constrained('users');
    $table->boolean('is_public')->default(false);
    $table->timestamps();
});
```

#### ب. إنشاء Service Class للتقارير
```php
class ReportGeneratorService
{
    public function generateReport($templateId, $filters = [])
    {
        // توليد التقرير بناءً على القالب
    }
    
    public function exportReport($reportData, $format)
    {
        // تصدير: PDF, Excel, CSV
    }
    
    public function scheduleReport($templateId, $schedule)
    {
        // جدولة التقرير (يومي، أسبوعي، شهري)
    }
}
```

#### ج. إنشاء جدول ScheduledReports
```php
Schema::create('scheduled_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('report_template_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->string('name');
    $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly']);
    $table->json('filters');
    $table->json('recipients'); // قائمة المستلمين
    $table->enum('format', ['pdf', 'excel', 'csv']);
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_run_at')->nullable();
    $table->timestamp('next_run_at');
    $table->timestamps();
});
```

---

## المرحلة 7: البنية السحابية والـ APIs (الأسبوع 23-26)

### 7.1 البنية السحابية
**المتطلبات:**
- Cloud-Native بالكامل
- قابلية التوسع
- تحديث مستمر

**التنفيذ:**

#### أ. إعداد Docker
```dockerfile
# Dockerfile
FROM php:8.2-fpm

# تثبيت الملحقات المطلوبة
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# إعداد Laravel
WORKDIR /app
COPY . /app
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache
RUN php artisan route:cache

CMD ["php-fpm"]
```

#### ب. إعداد Kubernetes (اختياري)
```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: erp-backend
spec:
  replicas: 3
  selector:
    matchLabels:
      app: erp-backend
  template:
    metadata:
      labels:
        app: erp-backend
    spec:
      containers:
      - name: laravel
        image: erp-backend:latest
        ports:
        - containerPort: 9000
```

#### ج. إعداد Queue Workers
```yaml
# docker-compose.yml
version: '3.8'
services:
  queue-worker:
    build: .
    command: php artisan queue:work --tries=3
    volumes:
      - .:/app
    depends_on:
      - redis
```

### 7.2 واجهات برمجة تطبيقات حديثة
**المتطلبات:**
- APIs قوية وموثقة
- سهولة التكامل
- دعم GraphQL (اختياري)

**التنفيذ:**

#### أ. تحسين هيكل API
```
/api/v2/
├── auth/
├── inventory/
│   ├── locations
│   ├── transfers
│   ├── batches
│   └── serial-numbers
├── orders/
│   ├── sales
│   ├── purchases
│   └── returns
├── warehouse/
│   ├── tasks
│   ├── bins
│   └── cycle-counts
├── crm/
│   ├── customers
│   └── interactions
├── suppliers/
│   ├── contracts
│   └── performance
├── reports/
│   ├── dashboards
│   └── custom
└── integrations/
    ├── shopify
    └── bigcommerce
```

#### ب. إضافة API Resources
```php
// Inventory Resources
class WarehouseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'location_type' => $this->location_type,
            'address' => $this->address,
            'capacity' => $this->capacity,
            'current_utilization' => $this->current_utilization,
            'is_active' => $this->is_active,
            'stock_summary' => new WarehouseStockSummaryResource($this),
        ];
    }
}
```

#### ج. إضافة GraphQL (اختياري)
```php
// استخدام lighthouse-php
type Warehouse {
    id: ID!
    name: String!
    code: String!
    locationType: String!
    stockSummary: StockSummary @field
}

type Query {
    warehouses: [Warehouse!]! @paginate
    warehouse(id: ID! @eq): Warehouse @find
}
```

#### د. توثيق API باستخدام OpenAPI/Swagger
```php
// استخدام l5-swagger
/**
 * @OA\Get(
 *     path="/api/v2/inventory/locations",
 *     summary="Get all inventory locations",
 *     tags={"Inventory"},
 *     @OA\Response(response="200", description="Successful operation")
 * )
 */
public function index()
{
    // ...
}
```

### 7.3 Event-Driven Architecture
**التنفيذ:**

#### أ. استخدام Laravel Events
```php
// Events
class InventoryLow extends Event
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public $warehouseId;
    public $productId;
    public $currentQuantity;
    public $reorderPoint;
}

// Listeners
class SendReorderAlert implements ShouldQueue
{
    public function handle(InventoryLow $event)
    {
        // إرسال تنبيه
    }
}
```

#### ب. استخدام WebSockets للتحديثات اللحظية
```php
// استخدام Laravel Echo + Pusher
Broadcast::channel('inventory.{warehouseId}', function ($user, $warehouseId) {
    return $user->hasAccessToWarehouse($warehouseId);
});

// بث التحديثات
broadcast(new InventoryUpdated($warehouseId, $productId, $quantity));
```

---

## المرحلة 8: تحسينات UX/UI (الأسبوع 27-30)

### 8.1 تصميم واجهة مستخدم حديثة
**المتطلبات:**
- تصميم بديهي
- تقليل وقت التدريب
- دعم RTL للعربية

**التنفيذ:**

#### أ. استخدام Element Plus المحسن
```vue
<template>
  <el-container>
    <el-header>
      <el-menu :default-active="activeMenu" mode="horizontal">
        <el-menu-item index="inventory">إدارة المخزون</el-menu-item>
        <el-menu-item index="orders">الطلبات</el-menu-item>
        <el-menu-item index="warehouse">المستودعات</el-menu-item>
      </el-menu>
    </el-header>
    <el-main>
      <router-view />
    </el-main>
  </el-container>
</template>
```

#### ب. إنشاء Layouts مخصصة
```
resources/js/layouts/
├── DashboardLayout.vue
├── InventoryLayout.vue
├── OrderLayout.vue
└── WarehouseLayout.vue
```

### 8.2 إمكانية التخصيص
**التنفيذ:**

#### أ. إنشاء جدول UserPreferences
```php
Schema::create('user_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('key');
    $table->text('value');
    $table->timestamps();
    
    $table->unique(['user_id', 'key']);
});
```

#### ب. دعم السمات (Themes)
```javascript
// themes.js
export const themes = {
    light: {
        primary: '#409EFF',
        success: '#67C23A',
        warning: '#E6A23C',
        danger: '#F56C6C',
    },
    dark: {
        primary: '#409EFF',
        success: '#67C23A',
        warning: '#E6A23C',
        danger: '#F56C6C',
    }
};
```

---

## المرحلة 9: الامتثال المحلي (الأسبوع 31-34)

### 9.1 دعم الفروع والكيانات المتعددة
**التنفيذ:**

#### أ. تعزيز نموذج Company
```php
// إضافة حقول جديدة
- parent_company_id
- legal_entity_type
- tax_id
- commercial_registration
- vat_number
- currency
- timezone
- locale
```

#### ب. إنشاء جدول CompanyBranches
```php
Schema::create('company_branches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->string('name');
    $table->string('code')->unique();
    $table->text('address');
    $table->string('city');
    $table->string('country');
    $table->string('phone');
    $table->string('email');
    $table->foreignId('warehouse_id')->nullable()->constrained();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 9.2 الفاتورة الإلكترونية
**التنفيذ:**

#### أ. إنشاء جدول EInvoices
```php
Schema::create('e_invoices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained();
    $table->string('invoice_uuid')->unique();
    $table->string('qr_code');
    $table->timestamp('signed_at');
    $table->timestamp('submitted_at')->nullable();
    $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected']);
    $table->text('response')->nullable();
    $table->timestamps();
});
```

#### ب. Service Class للفاتورة الإلكترونية
```php
class EInvoiceService
{
    public function generateInvoice($invoiceId)
    {
        // توليد الفاتورة الإلكترونية
        // التوقيع الرقمي
        // توليد QR Code
    }
    
    public function submitToAuthority($eInvoiceId)
    {
        // إرسال للجهة الضريبية
    }
}
```

---

## المرحلة 10: إدارة التغيير (الأسبوع 35-38)

### 10.1 خطة التدريب
**المواد:**
- دليل المستخدم (PDF)
- فيديوهات تعليمية
- جلسات تدريب مباشرة
- بيئة تجريبية (Sandbox)

### 10.2 دعم المستخدمين
**التنفيذ:**

#### أ. نظام تذاكر الدعم محسن
```php
// تعزيز نموذج Ticket
- priority (low, medium, high, critical)
- category (technical, functional, billing)
- sla_response_time
- sla_resolution_time
- satisfaction_rating
```

#### ب. معرفة قاعدة (Knowledge Base)
```php
Schema::create('knowledge_base_articles', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('content');
    $table->foreignId('category_id')->nullable();
    $table->integer('views')->default(0);
    $table->boolean('is_published')->default(false);
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});
```

---

## الجدول الزمني المختصر

| المرحلة | المدة | المخرجات الرئيسية |
|---------|-------|-------------------|
| 1. التحليل والتخطيط | أسبوع 1-2 | خارطة طريق مفصلة |
| 2. إدارة المخزون | أسبوع 3-6 | نظام مخزون متقدم |
| 3. الطلبات والمبيعات | أسبوع 7-10 | دورة مبيعات كاملة |
| 4. WMS | أسبوع 11-14 | نظام مستودعات آلي |
| 5. CRM والموردين | أسبوع 15-18 | منصة علاقات متكاملة |
| 6. التقارير | أسبوع 19-22 | لوحات معلومات تفاعلية |
| 7. السحابة و APIs | أسبوع 23-26 | بنية سحابية حديثة |
| 8. UX/UI | أسبوع 27-30 | واجهة مستخدم محسنة |
| 9. الامتثال | أسبوع 31-34 | توافق محلي كامل |
| 10. إدارة التغيير | أسبوع 35-38 | تدريب ودعم |

**الإجمالي:** 38 أسبوع (~9 أشهر)

---

## التقنيات المقترحة

### Backend
- Laravel 12
- PHP 8.2+
- MySQL 8.0+ / PostgreSQL
- Redis للتخزين المؤقت والqueues
- Elasticsearch للبحث المتقدم

### Frontend
- Vue.js 3
- Element Plus
- Pinia لإدارة الحالة
- Vue Router
- Tailwind CSS

### DevOps
- Docker
- Kubernetes (اختياري)
- GitHub Actions للـ CI/CD
- AWS / Azure / Google Cloud

### المراقبة
- Laravel Telescope
- Sentry لتتبع الأخطاء
- New Relic / Datadog لمراقبة الأداء

---

## المخاطر والتحديات

### المخاطر التقنية
- **تعقيد الترحيل:** قد يكون ترحيل البيانات الحالية صعباً
- **الأداء:** قد يؤثر حجم البيانات الكبير على الأداء
- **التكامل:** صعوبة التكامل مع الأنظمة القديمة

### المخاطر التشغيلية
- **مقاومة التغيير:** الموظفون قد يقاومون النظام الجديد
- **التدريب:** يحتاج وقت وجهد لتدريب الموظفين
- **الاعتمادية:** النظام يجب أن يكون موثوقاً جداً

### التخفيف
- اختبار شامل قبل الإطلاق
- إطلاق تدريجي (Phased Rollout)
- دعم فني مستمر
- خطة طوارئ (Rollback Plan)

---

## الخاتمة

هذه الخارطة الطريق توفر إطاراً شاملاً لتحويل نظام ERP الحالي إلى منصة تشغيلية حديثة ومتكاملة. يجب مراجعة وتحديث الخطة بشكل دوري بناءً على التقدم والمتغيرات.

**النجاح يعتمد على:**
1. التزام الإدارة العليا
2. مشاركة المستخدمين في عملية التطوير
3. اختبار شامل
4. تدريب فعال
5. دعم مستمر
