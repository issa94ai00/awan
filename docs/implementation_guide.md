# دليل التنفيذ الشامل - نظام ربط المنتجات بالمستودعات
# Comprehensive Implementation Guide - Product-Warehouse Assignment System

## جدول المحتويات / Table of Contents

1. [نظرة عامة](#نظرة-عامة-overview)
2. [المتطلبات الأساسية](#المتطلبات-الأساسية-prerequisites)
3. [خطوات التثبيت](#خطوات-التثبيت-installation-steps)
4. [هيكلية قاعدة البيانات](#هيكلية-قاعدة-البيانات-database-structure)
5. [استخدام API](#استخدام-api-api-usage)
6. [استخدام الخدمات](#استخدام-الخدمات-service-usage)
7. [سيناريو الاختبار](#سيناريو-الاختبار-acceptance-scenario)
8. [اعتبارات الأداء](#اعتبارات-الأداء-performance-considerations)
9. [الخطوات التالية](#الخطوات-التالية-next-steps)

---

## نظرة عامة / Overview

هذا النظام يوفر بنية تحتية مرنة وديناميكية لربط المنتجات بالمستودعات في نظام الـ ERP، مع فصل البيانات التجارية عن البيانات التشغيلية ودعم التخطيط الاحترافي (MRP) وعمليات الانتقاء والاستلام.

This system provides a flexible and dynamic infrastructure for linking products to warehouses in the ERP system, separating commercial data from operational data and supporting professional planning (MRP) and picking/receiving operations.

### الميزات الرئيسية / Key Features

- **فصل البيانات:** فصل المنتج (تجاري) عن الصنف (تشغيلي)
- **تخطيط لكل مستودع:** بيانات تخطيط مختلفة لكل مستودع لنفس المنتج
- **دعم MRP:** حساب الاحتياجات على مستوى (مستودع + صنف)
- **انتقاء موجه:** FIFO مع مراعاة القرب الجغرافي
- **منتجات مركبة:** دعم المنتجات المجمعة (Kitted Products)
- **تواريخ مستقبلية:** دعم ربط المنتجات بتواريخ مستقبلية

---

## المتطلبات الأساسية / Prerequisites

- Laravel 10.x أو أحدث
- MySQL 8.0 أو أحدث
- PHP 8.1 أو أحدث
- Composer
- Node.js & NPM (للواجهة الأمامية)

---

## خطوات التثبيت / Installation Steps

### 1. تشغيل الترحيلات / Run Migrations

```bash
# تشغيل جميع الترحيلات الجديدة
php artisan migrate

# أو تشغيل ترحيلات محددة
php artisan migrate --path=database/migrations/2026_08_07_000001_create_product_warehouse_assignments_table.php
php artisan migrate --path=database/migrations/2026_08_07_000002_create_bin_assignments_table.php
php artisan migrate --path=database/migrations/2026_08_07_000003_create_product_components_table.php
```

### 2. تحديث النماذج / Update Models

تم تحديث النماذج التالية بالفعل:
- `Product.php` - أضيفت علاقات warehouseAssignments و components
- `Warehouse.php` - أضيفت علاقات productAssignments
- النماذج الجديدة:
  - `ProductWarehouseAssignment.php`
  - `BinAssignment.php`
  - `ProductComponent.php`

### 3. تحديث المسارات / Update Routes

تم تحديث `routes/api.php` بإضافة مسارات API الجديدة لربط المنتجات بالمستودعات.

### 4. نشر الخدمات / Publish Services

الخدمات متاحة في:
- `app/Services/Inventory/MrpService.php`
- `app/Services/Inventory/PickingService.php`
- `app/Services/Inventory/CompositeProductService.php`

---

## هيكلية قاعدة البيانات / Database Structure

### الجداول الجديدة / New Tables

#### 1. product_warehouse_assignments
الجدول الرئيسي لربط المنتجات بالمستودعات مع بيانات التخطيط.

```php
Schema::create('product_warehouse_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products');
    $table->foreignId('product_variant_id')->nullable()->constrained('product_variants');
    $table->foreignId('warehouse_id')->constrained('warehouses');
    $table->date('effective_date')->default(now());
    $table->date('expiry_date')->nullable();
    $table->boolean('is_active')->default(true);
    $table->enum('replenishment_method', ['purchase', 'manufacture', 'internal_distribution', 'warehouse_transfer']);
    $table->enum('planning_method', ['rop', 'mrp']);
    $table->integer('min_stock_level')->default(0);
    $table->integer('max_stock_level')->default(0);
    $table->integer('safety_stock')->default(0);
    $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
    $table->integer('lead_time_days')->default(7);
    $table->foreignId('primary_bin_id')->nullable()->constrained('warehouse_bins');
    $table->enum('putaway_strategy', ['fifo', 'fefo', 'similarity', 'weight_based', 'volume_based']);
    $table->boolean('auto_reorder_enabled')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['product_id', 'warehouse_id', 'effective_date']);
});
```

#### 2. bin_assignments
جدول لإدارة المواقع الرئيسية والبديلة لكل صنف في المستودع.

```php
Schema::create('bin_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_warehouse_assignment_id')->constrained('product_warehouse_assignments');
    $table->foreignId('bin_id')->constrained('warehouse_bins');
    $table->boolean('is_primary')->default(false);
    $table->integer('priority_order')->default(0);
    $table->decimal('capacity_percentage', 5, 2)->default(0);
    $table->timestamps();
    $table->unique(['product_warehouse_assignment_id', 'is_primary']);
});
```

#### 3. product_components
جدول لإدارة مكونات المنتجات المركبة.

```php
Schema::create('product_components', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_product_id')->constrained('products');
    $table->foreignId('component_product_id')->constrained('products');
    $table->integer('quantity_required')->default(1);
    $table->boolean('is_optional')->default(false);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->unique(['parent_product_id', 'component_product_id']);
});
```

---

## استخدام API / API Usage

### 1. إنشاء ربط منتج بمستودع / Create Product-Warehouse Assignment

```bash
POST /api/v1/admin/product-warehouse-assignments
Authorization: Bearer {token}

{
    "product_id": 1,
    "product_variant_id": null,
    "warehouse_id": 1,
    "effective_date": "2026-08-07",
    "expiry_date": null,
    "is_active": true,
    "replenishment_method": "purchase",
    "planning_method": "mrp",
    "min_stock_level": 50,
    "max_stock_level": 200,
    "safety_stock": 10,
    "supplier_id": 1,
    "lead_time_days": 5,
    "primary_bin_id": 1,
    "putaway_strategy": "fifo",
    "auto_reorder_enabled": true,
    "notes": "ربط للمستودع الرئيسي"
}
```

### 2. الحصول على الرصيد الحالي للمنتج / Get Real-Time Stock Balance

```bash
GET /api/v1/admin/product-warehouse-assignments/products/{productId}/stock-balance
Authorization: Bearer {token}

Response:
{
    "success": true,
    "data": {
        "product_id": 1,
        "total_stock": 250,
        "total_available": 230,
        "warehouses": [
            {
                "warehouse_id": 1,
                "warehouse_name": "المستودع أ",
                "warehouse_code": "WH-A",
                "warehouse_location": {
                    "city": "الرياض",
                    "latitude": 24.7136,
                    "longitude": 46.6753
                },
                "quantity": 150,
                "available_quantity": 140,
                "reserved_quantity": 10,
                "min_stock_level": 50,
                "max_stock_level": 200,
                "safety_stock": 10,
                "is_below_min": false,
                "is_below_reorder": false,
                "last_updated": "2026-08-07T10:30:00Z"
            }
        ]
    }
}
```

### 3. تحديث الرصيد بعد عملية إيداع/انتقاء / Update Stock Balance

```bash
POST /api/v1/admin/product-warehouse-assignments/update-stock-balance
Authorization: Bearer {token}

{
    "warehouse_id": 1,
    "product_id": 1,
    "product_variant_id": null,
    "bin_id": 1,
    "quantity_change": 50,
    "movement_type": "putaway",
    "reference_type": "purchase_receipt",
    "reference_id": 123,
    "batch_number": "BATCH-2026-001",
    "expiry_date": "2027-08-07",
    "notes": "استلام من المورد X"
}
```

### 4. الحصول على تنبيهات إعادة الطلب / Get Reorder Alerts

```bash
GET /api/v1/admin/product-warehouse-assignments/reorder-alerts
Authorization: Bearer {token}

Response:
{
    "success": true,
    "data": [
        {
            "assignment_id": 1,
            "product": {
                "id": 1,
                "name": "هاتف ذكي X",
                "sku": "PHX-001"
            },
            "warehouse": {
                "id": 1,
                "name": "المستودع أ",
                "code": "WH-A"
            },
            "current_stock": 45,
            "min_stock_level": 50,
            "safety_stock": 10,
            "reorder_quantity": 15,
            "replenishment_method": "purchase",
            "supplier": {
                "id": 1,
                "name": "مورد X"
            },
            "lead_time_days": 5
        }
    ]
}
```

### 5. الحصول على المستودع الموصى به / Get Recommended Warehouse

```bash
GET /api/v1/admin/product-warehouse-assignments/recommended-warehouse?product_id=1&customer_latitude=24.7136&customer_longitude=46.6753&quantity=10
Authorization: Bearer {token}

Response:
{
    "success": true,
    "data": {
        "assignment": {...},
        "distance_km": 5.2
    }
}
```

---

## استخدام الخدمات / Service Usage

### 1. خدمة MRP / MRP Service

```php
use App\Services\Inventory\MrpService;

$mrpService = new MrpService();

// تشغيل MRP لمنتج محدد
$results = $mrpService->runMrpForProduct($productId, 90);

// تشغيل MRP لمستودع محدد
$results = $mrpService->runMrpForWarehouse($warehouseId, 90);

// تنفيذ توصيات MRP
$orders = $mrpService->executeMrpRecommendations($assignmentId, ['urgent', 'warning']);
```

### 2. خدمة الانتقاء / Picking Service

```php
use App\Services\Inventory\PickingService;

$pickingService = new PickingService();

// الحصول على أفضل مستودع للانتقاء
$bestWarehouse = $pickingService->getBestWarehouseForPicking(
    $productId,
    $customerLatitude,
    $customerLongitude,
    $quantity
);

// إنشاء خطة انتقاء لأمر مبيعات
$pickingPlan = $pickingService->generatePickingPlan(
    $salesOrderId,
    $customerLatitude,
    $customerLongitude
);

// تحسين مسار الانتقاء داخل المستودع
$optimizedRoute = $pickingService->optimizePickingRoute($pickingListId);

// تأكيد إتمام الانتقاء
$result = $pickingService->confirmPicking($pickingListId, $pickerId);
```

### 3. خدمة المنتجات المركبة / Composite Product Service

```php
use App\Services\Inventory\CompositeProductService;

$compositeService = new CompositeProductService();

// التحقق من أن المنتج مركب
$isComposite = $compositeService->isCompositeProduct($productId);

// الحصول على مكونات المنتج
$components = $compositeService->getProductComponents($productId);

// التحقق من إمكانية التجميع
$availability = $compositeService->canAssembleProduct($productId, $quantity, $warehouseId);

// الحصول على أفضل مستودع للتجميع
$bestWarehouse = $compositeService->getBestWarehouseForAssembly(
    $productId,
    $quantity,
    $customerLatitude,
    $customerLongitude
);

// إنشاء أمر تجميع
$assemblyOrder = $compositeService->createAssemblyOrder(
    $productId,
    $quantity,
    $warehouseId,
    $assemblyAreaId
);

// إكمال أمر التجميع
$result = $compositeService->completeAssemblyOrder($assemblyOrderId);

// تفكيك منتج مركب
$result = $compositeService->disassembleProduct($productId, $quantity, $warehouseId);

// الحصول على قائمة المواد (BOM)
$bom = $compositeService->getBillOfMaterials($productId);

// حساب تكلفة المنتج المركب
$cost = $compositeService->calculateCompositeProductCost($productId);
```

---

## سيناريو الاختبار / Acceptance Scenario

### السيناريو المطلوب / Required Scenario

*"المنتج (هاتف ذكي) مسجل في المستودعين (أ) و (ب). في المستودع (أ)، طريقة التزويد (شراء) والمورد (X) والمهلة (5 أيام)، والحد الأدنى (50 قطعة). في المستودع (ب)، طريقة التزويد (نقل داخلي من أ) والمهلة (يوم واحد) والحد الأدنى (10 قطع). عند تنفيذ أمر مبيعات لعنوان قريب من ب، يجب أن يوصي النظام بالانتقاء من ب، وعندما يهبط المخزون في ب إلى 9 قطع، يجب أن يولد الـ MRP طلب نقل داخلي من أ إلى ب تلقائياً."*

### تنفيذ السيناريو / Scenario Implementation

#### الخطوة 1: إنشاء المنتج والمستودعات

```php
// إنشاء المنتج
$product = Product::create([
    'name' => 'هاتف ذكي X',
    'name_ar' => 'هاتف ذكي X',
    'name_en' => 'Smartphone X',
    'sku' => 'PHX-001',
    'price' => 2999.00,
    'cost_price' => 2000.00,
]);

// إنشاء المستودعات
$warehouseA = Warehouse::create([
    'name' => 'المستودع أ',
    'code' => 'WH-A',
    'city' => 'الرياض',
    'country' => 'السعودية',
    'latitude' => 24.7136,
    'longitude' => 46.6753,
    'is_active' => true,
]);

$warehouseB = Warehouse::create([
    'name' => 'المستودع ب',
    'code' => 'WH-B',
    'city' => 'الدمام',
    'country' => 'السعودية',
    'latitude' => 26.4267,
    'longitude' => 50.0834,
    'is_active' => true,
]);

// إنشاء المورد
$supplier = Supplier::create([
    'name' => 'مورد X',
    'address' => 'الرياض',
    'latitude' => 24.7136,
    'longitude' => 46.6753,
]);
```

#### الخطوة 2: إنشاء روابط المنتج بالمستودعات

```php
// ربط المنتج بالمستودع أ
$assignmentA = ProductWarehouseAssignment::create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouseA->id,
    'effective_date' => now(),
    'is_active' => true,
    'replenishment_method' => 'purchase',
    'planning_method' => 'mrp',
    'min_stock_level' => 50,
    'max_stock_level' => 200,
    'safety_stock' => 10,
    'supplier_id' => $supplier->id,
    'lead_time_days' => 5,
    'putaway_strategy' => 'fifo',
    'auto_reorder_enabled' => true,
]);

// ربط المنتج بالمستودع ب
$assignmentB = ProductWarehouseAssignment::create([
    'product_id' => $product->id,
    'warehouse_id' => $warehouseB->id,
    'effective_date' => now(),
    'is_active' => true,
    'replenishment_method' => 'warehouse_transfer',
    'planning_method' => 'mrp',
    'min_stock_level' => 10,
    'max_stock_level' => 50,
    'safety_stock' => 5,
    'lead_time_days' => 1,
    'putaway_strategy' => 'fifo',
    'auto_reorder_enabled' => true,
]);

// إنشاء سجلات المخزون الأولية
WarehouseInventory::create([
    'warehouse_id' => $warehouseA->id,
    'product_id' => $product->id,
    'quantity' => 150,
    'available_quantity' => 150,
    'reorder_point' => 50,
    'safety_stock' => 10,
]);

WarehouseInventory::create([
    'warehouse_id' => $warehouseB->id,
    'product_id' => $product->id,
    'quantity' => 20,
    'available_quantity' => 20,
    'reorder_point' => 10,
    'safety_stock' => 5,
]);
```

#### الخطوة 3: اختبار الانتقاء من المستودع الأقرب

```php
use App\Services\Inventory\PickingService;

$pickingService = new PickingService();

// عنوان العميل قريب من المستودع ب (الدمام)
$customerLatitude = 26.4267;
$customerLongitude = 50.0834;

// الحصول على أفضل مستودع للانتقاء
$bestWarehouse = $pickingService->getBestWarehouseForPicking(
    $product->id,
    $customerLatitude,
    $customerLongitude,
    5 // الكمية المطلوبة
);

// النتيجة المتوقعة: المستودع ب (أقرب جغرافياً)
assert($bestWarehouse->warehouse_id === $warehouseB->id);
```

#### الخطوة 4: محاكاة انخفاض المخزون في المستودع ب

```php
// تحديث المخزون في المستودع ب إلى 9 قطع
$inventoryB = WarehouseInventory::where('warehouse_id', $warehouseB->id)
    ->where('product_id', $product->id)
    ->first();

$inventoryB->quantity = 9;
$inventoryB->available_quantity = 9;
$inventoryB->save();
```

#### الخطوة 5: تشغيل MRP وتوليد طلب النقل

```php
use App\Services\Inventory\MrpService;

$mrpService = new MrpService();

// تشغيل MRP للمنتج في المستودع ب
$mrpResult = $mrpService->runMrpForProduct($product->id, 90);

// البحث عن توصية للمستودع ب
$warehouseBResult = collect($mrpResult)->firstWhere('warehouse.id', $warehouseB->id);

// التحقق من وجود توصية بنقل داخلي
$transferRecommendation = collect($warehouseBResult['recommendations'])
    ->firstWhere('action', 'create_transfer');

assert($transferRecommendation !== null);

// تنفيذ التوصية
$executedOrders = $mrpService->executeMrpRecommendations(
    $assignmentB->id,
    ['transfer']
);

// النتيجة المتوقعة: إنشاء طلب نقل من المستودع أ إلى ب
assert(count($executedOrders) > 0);
assert($executedOrders[0]['type'] === 'inventory_transfer');
assert($executedOrders[0]['from_warehouse_id'] === $warehouseA->id);
assert($executedOrders[0]['to_warehouse_id'] === $warehouseB->id);
```

---

## اعتبارات الأداء / Performance Considerations

### 1. الفهرسة / Indexing

تم إضافة الفهارس التالية لتحسين الأداء:

- فهرس فريد على (product_id, warehouse_id, effective_date)
- فهرس مركب على (warehouse_id, is_active)
- فهرس على (effective_date, expiry_date)
- فهرس جغرافي على (latitude, longitude) للمستودعات

### 2. التخزين المؤقت / Caching

يُنصح باستخدام Redis للتخزين المؤقت للبيانات المتكررة:

```php
// تخزين الرصيد الحالي مؤقتاً
Cache::remember("stock_balance_{$productId}", 300, function () use ($productId) {
    return $assignmentController->getRealTimeStockBalance($productId);
});
```

### 3. معالجة الدفعات / Batch Processing

للأنظمة الكبيرة (10,000+ منتج، 20+ مستودع):

```php
// معالجة MRP في الخلفية
use Illuminate\Bus\Batch;
use App\Jobs\RunMrpForWarehouse;

$batch = Bus::batch([
    new RunMrpForWarehouse($warehouseId),
    new RunMrpForWarehouse($warehouseId2),
])->then(function (Batch $batch) {
    // عند اكتمال جميع المهام
})->dispatch();
```

### 4. تحسين الاستعلامات / Query Optimization

```php
// استخدام eager loading لتجنب مشكلة N+1
$assignments = ProductWarehouseAssignment::with([
    'product',
    'warehouse',
    'inventory',
    'supplier'
])->get();

// استخدام pagination للنتائج الكبيرة
$assignments = ProductWarehouseAssignment::paginate(50);
```

---

## الخطوات التالية / Next Steps

### 1. إنشاء جداول التجميع / Create Assembly Tables

```php
Schema::create('product_assembly_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('parent_product_id')->constrained('products');
    $table->foreignId('warehouse_id')->constrained('warehouses');
    $table->foreignId('assembly_area_id')->nullable();
    $table->integer('quantity_to_assemble');
    $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('product_assembly_order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assembly_order_id')->constrained('product_assembly_orders');
    $table->foreignId('component_product_id')->constrained('products');
    $table->integer('quantity_required');
    $table->integer('quantity_reserved')->default(0);
    $table->integer('quantity_consumed')->default(0);
    $table->timestamps();
});
```

### 2. إنشاء واجهة المستخدم / Create User Interface

استخدم ملف UI Mockup في `docs/ui_mockup_product_warehouse_assignment.md` كمرجع لبناء الواجهة الأمامية.

### 3. إضافة الاختبارات / Add Tests

```php
// tests/Feature/ProductWarehouseAssignmentTest.php
public function test_can_create_assignment()
{
    $response = $this->postJson('/api/v1/admin/product-warehouse-assignments', [
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        // ... other fields
    ]);

    $response->assertStatus(201);
}

public function test_mrp_generates_transfer_recommendation()
{
    // Test the MRP transfer logic
}

public function test_picking_uses_geographic_proximity()
{
    // Test the geographic proximity picking logic
}
```

### 4. إعداد Cron Jobs / Setup Cron Jobs

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // تشغيل MRP يومياً
    $schedule->call(function () {
        $mrpService = new MrpService();
        $mrpService->runMrpForAllProducts();
    })->dailyAt('02:00');

    // تحديث متوسط المبيعات اليومية أسبوعياً
    $schedule->call(function () {
        ProductWarehouseAssignment::all()->each(function ($assignment) {
            $assignment->inventory->first()?->updateAverageDailySales();
        });
    })->weekly();
}
```

### 5. مراقبة وتنبيهات / Monitoring & Alerts

```php
// إعداد تنبيهات للمخزون المنخفض
use App\Notifications\LowStockAlert;

$assignments = ProductWarehouseAssignment::active()
    ->get()
    ->filter(fn($a) => $a->isBelowReorderPoint());

foreach ($assignments as $assignment) {
    $assignment->warehouse->manager->notify(new LowStockAlert($assignment));
}
```

---

## ملخص الملفات المضافة / Summary of Added Files

### قاعدة البيانات / Database
- `database/migrations/2026_08_07_000001_create_product_warehouse_assignments_table.php`
- `database/migrations/2026_08_07_000002_create_bin_assignments_table.php`
- `database/migrations/2026_08_07_000003_create_product_components_table.php`

### النماذج / Models
- `app/Models/ProductWarehouseAssignment.php`
- `app/Models/BinAssignment.php`
- `app/Models/ProductComponent.php`

### النماذج المحدثة / Updated Models
- `app/Models/Product.php` (أضيفت علاقات جديدة)
- `app/Models/Warehouse.php` (أضيفت علاقات جديدة)

### وحدات التحكم / Controllers
- `app/Http/Controllers/Api/ProductWarehouseAssignmentController.php`

### الخدمات / Services
- `app/Services/Inventory/MrpService.php`
- `app/Services/Inventory/PickingService.php`
- `app/Services/Inventory/CompositeProductService.php`

### المسارات / Routes
- `routes/api.php` (أضيفت مسارات API الجديدة)

### الوثائق / Documentation
- `docs/product_warehouse_erd.md` (مخطط ERD)
- `docs/ui_mockup_product_warehouse_assignment.md` (تصميم الواجهة)
- `docs/implementation_guide.md` (هذا الدليل)

---

## الدعم الفني / Technical Support

للأسئلة أو المشاكل، راجع:
- ملفات التعليقات في الكود
- وثائق Laravel الرسمية
- سجلات النظام في `storage/logs/`

---

## ملاحظات مهمة / Important Notes

1. **النسخ الاحتياطي:** قبل تشغيل الترحيلات، تأخذ نسخة احتياطية من قاعدة البيانات
2. **الاختبار:** اختبر النظام في بيئة staging قبل النشر إلى production
3. **الأداء:** راقب أداء الاستعلامات بعد النشر وأضف فهارس إضافية إذا لزم الأمر
4. **الأمان:** تأكد من تطبيق صلاحيات API بشكل صحيح
5. **التدريب:** درب المستخدمين على الواجهة الجديدة قبل إطلاقها

---

**تم الانتهاء من التنفيذ / Implementation Completed**
