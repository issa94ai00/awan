<?php
/*
 * Read-only business-logic diagnostics: does the stored data obey the formulas
 * and state rules the code applies? Run through tinker (see build.sh); writes
 * JSON to $LOGIC_OUT. SELECT queries only.
 *
 * Formulas mirrored from the code:
 *  - Invoice line   total_price = unit_price × quantity                       (InvoiceController::store)
 *  - Invoice        total = max(0, subtotal + tax − discount + additional_charges)
 *                   discount% → discount = subtotal × %;  tax% → tax = (subtotal − discount) × %   (resolveCharges)
 *  - Sales order    subtotal = Σ(unit_price × qty − line discount + line tax)
 *                   total = subtotal − discount + tax + shipping_cost          (SalesOrderWorkflowService::recalculateTotals)
 *  - Quote          total = subtotal − discount + tax                          (QuoteController)
 *  - Purchase order line total_price = quantity × unit_price; total = subtotal + tax − discount
 *  - Credit note    applied_to_invoice + refunded_amount + store_credit_amount = total  (RmaSettlementService)
 *  - Reservations   warehouse_inventory.reserved_quantity is raised on SO confirmation (InventoryService::reserve)
 *                   and consumed on shipment; open allocations of confirmed/processing orders should match it.
 *  - FIFO           Σ inventory_cost_layers.remaining_quantity per product/warehouse = stock on hand.
 */

use Illuminate\Support\Facades\DB;

$q = fn (string $sql, array $b = []) => array_map(fn ($r) => (array) $r, DB::select($sql, $b));
$checks = [];
$errors = [];
$safe = function (callable $fn, $fallback = null) use (&$errors) { try { return $fn(); } catch (\Throwable $e) { $errors[] = $e->getMessage(); return $fallback; } };
$add = function (string $id, string $cat, string $sev, string $title, string $why, string $fix, array $items) use (&$checks, &$errors) {
    $err = $errors; $errors = [];
    $checks[] = ['id' => $id, 'cat' => $cat, 'sev' => $err ? 'error' : ($items ? $sev : 'ok'), 'error' => $err ? mb_substr(implode(' | ', array_unique($err)), 0, 300) : null,
        'title' => $title, 'why' => $why, 'fix' => $fix, 'count' => count($items), 'items' => array_slice($items, 0, 200)];
};
$f = fn ($v) => +round((float) $v, 4);
$E = 0.011; // tolerance: one cent plus rounding

/* ===================== CALCULATIONS ===================== */
$items = [];
$safe(function () use (&$items, $q, $f, $E) {
    foreach ($q("SELECT it.id, i.invoice_number, it.product_name, it.quantity, it.unit_price, it.total_price FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id
        WHERE ABS(it.total_price - it.unit_price * it.quantity) > {$E}") as $x)
        $items[] = ['table' => 'invoice_items', 'column' => 'total_price', 'detail' => "فاتورة {$x['invoice_number']} — {$x['product_name']}: {$f($x['quantity'])} × {$f($x['unit_price'])} ≠ {$f($x['total_price'])}"];
    foreach ($q("SELECT i.id, i.invoice_number, i.subtotal, SUM(it.total_price) s FROM invoices i JOIN invoice_items it ON it.invoice_id = i.id GROUP BY i.id, i.invoice_number, i.subtotal HAVING ABS(i.subtotal - s) > {$E}") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'subtotal', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}): المجموع {$f($x['subtotal'])} ≠ مجموع البنود {$f($x['s'])}"];
    foreach ($q("SELECT id, invoice_number, subtotal, tax, discount, COALESCE(additional_charges,0) ch, total FROM invoices
        WHERE ABS(total - GREATEST(0, subtotal + tax - discount + COALESCE(additional_charges,0))) > {$E}") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'total', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}): {$f($x['subtotal'])} + ضريبة {$f($x['tax'])} − خصم {$f($x['discount'])} + رسوم {$f($x['ch'])} ≠ الإجمالي {$f($x['total'])}"];
    foreach ($q("SELECT id, invoice_number, subtotal, discount, discount_percent FROM invoices WHERE discount_percent IS NOT NULL AND ABS(discount - GREATEST(0,subtotal) * discount_percent / 100) > {$E}") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'discount', 'detail' => "فاتورة {$x['invoice_number']}: خصم {$f($x['discount_percent'])}% من {$f($x['subtotal'])} يجب أن يكون " . $f($x['subtotal'] * $x['discount_percent'] / 100) . " وهو {$f($x['discount'])}"];
    foreach ($q("SELECT id, invoice_number, subtotal, discount, tax, tax_percent FROM invoices WHERE tax_percent IS NOT NULL AND ABS(tax - GREATEST(0, subtotal - discount) * tax_percent / 100) > {$E}") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'tax', 'detail' => "فاتورة {$x['invoice_number']}: ضريبة {$f($x['tax_percent'])}% على " . $f($x['subtotal'] - $x['discount']) . " يجب أن تكون " . $f(max(0, $x['subtotal'] - $x['discount']) * $x['tax_percent'] / 100) . " وهي {$f($x['tax'])}"];
});
$add('calc_invoices', 'calc', 'critical', 'حسابات الفواتير لا تطابق معادلة النظام',
    'البند = الكمية × السعر؛ الإجمالي = المجموع + الضريبة − الخصم + الرسوم؛ الضريبة تُحسب بعد الخصم. أي اختلاف يعني تعديلاً يدوياً أو مساراً برمجياً يتجاوز المعادلة.',
    'إعادة حفظ الفاتورة من شاشة التعديل لإعادة الحساب، ومراجعة المسار الذي أنشأها.', $items);

$items = [];
$safe(function () use (&$items, $q, $f, $E) {
    foreach ($q("SELECT s.id, s.order_number, s.subtotal, SUM(i.unit_price * i.quantity - COALESCE(i.discount,0) + COALESCE(i.tax,0)) calc FROM sales_orders s JOIN sales_order_items i ON i.sales_order_id = s.id
        GROUP BY s.id, s.order_number, s.subtotal HAVING ABS(s.subtotal - calc) > {$E}") as $x)
        $items[] = ['table' => 'sales_orders', 'column' => 'subtotal', 'detail' => "أمر {$x['order_number']} (#{$x['id']}): المجموع {$f($x['subtotal'])} ≠ مجموع البنود {$f($x['calc'])}"];
    foreach ($q("SELECT id, order_number, subtotal, discount, tax, COALESCE(shipping_cost,0) sh, total FROM sales_orders
        WHERE ABS(total - (subtotal - COALESCE(discount,0) + COALESCE(tax,0) + COALESCE(shipping_cost,0))) > {$E}") as $x)
        $items[] = ['table' => 'sales_orders', 'column' => 'total', 'detail' => "أمر {$x['order_number']} (#{$x['id']}): {$f($x['subtotal'])} − {$f($x['discount'])} + {$f($x['tax'])} + شحن {$f($x['sh'])} ≠ {$f($x['total'])}"];
    foreach ($q("SELECT id, quote_number, subtotal, discount, tax, total FROM quotes WHERE ABS(total - (subtotal - COALESCE(discount,0) + COALESCE(tax,0))) > {$E}") as $x)
        $items[] = ['table' => 'quotes', 'column' => 'total', 'detail' => "عرض {$x['quote_number']}: {$f($x['subtotal'])} − {$f($x['discount'])} + {$f($x['tax'])} ≠ {$f($x['total'])}"];
    foreach ($q("SELECT s.id, s.order_number, s.total so_total, i.invoice_number, i.total inv_total FROM sales_orders s JOIN invoices i ON i.sales_order_id = s.id AND i.status <> 'cancelled'
        WHERE s.status <> 'cancelled' AND ABS(s.total - i.total) > {$E}") as $x)
        $items[] = ['table' => 'sales_orders', 'column' => 'total', 'detail' => "أمر {$x['order_number']} = {$f($x['so_total'])} لكن فاتورته {$x['invoice_number']} = {$f($x['inv_total'])}"];
});
$add('calc_orders', 'calc', 'warning', 'حسابات أوامر البيع وعروض الأسعار',
    'إجمالي الأمر = مجموع البنود − الخصم + الضريبة + الشحن، ويجب أن يساوي إجمالي فاتورته؛ الفرق يعني أن الأمر عُدّل بعد الفوترة أو أن الفاتورة لم تُحدَّث.',
    'مراجعة الأمر والفاتورة المذكورين وتوحيد المبلغ (تصحيح الفاتورة يُنشئ قيد تصحيح تلقائياً).', $items);

$items = [];
$safe(function () use (&$items, $q, $f, $E) {
    foreach ($q("SELECT p.order_number, i.product_name, i.quantity, i.unit_price, i.total_price FROM purchase_order_items i JOIN purchase_orders p ON p.id = i.purchase_order_id
        WHERE ABS(i.total_price - i.quantity * i.unit_price) > {$E}") as $x)
        $items[] = ['table' => 'purchase_order_items', 'column' => 'total_price', 'detail' => "أمر شراء {$x['order_number']} — {$x['product_name']}: {$f($x['quantity'])} × {$f($x['unit_price'])} ≠ {$f($x['total_price'])}"];
    foreach ($q("SELECT p.id, p.order_number, p.subtotal, p.tax, p.discount, p.total, SUM(i.total_price) s FROM purchase_orders p JOIN purchase_order_items i ON i.purchase_order_id = p.id
        GROUP BY p.id, p.order_number, p.subtotal, p.tax, p.discount, p.total HAVING ABS(p.subtotal - s) > {$E} OR ABS(p.total - (p.subtotal + COALESCE(p.tax,0) - COALESCE(p.discount,0))) > {$E}") as $x)
        $items[] = ['table' => 'purchase_orders', 'column' => 'total', 'detail' => "أمر شراء {$x['order_number']}: مجموع البنود {$f($x['s'])}، المجموع المسجل {$f($x['subtotal'])}، الإجمالي {$f($x['total'])}"];
    foreach ($q("SELECT credit_note_number, total, applied_to_invoice a, refunded_amount r, store_credit_amount sc FROM credit_notes WHERE deleted_at IS NULL
        AND ABS(total - (COALESCE(applied_to_invoice,0) + COALESCE(refunded_amount,0) + COALESCE(store_credit_amount,0))) > {$E}") as $x)
        $items[] = ['table' => 'credit_notes', 'column' => 'total', 'detail' => "إشعار {$x['credit_note_number']}: {$f($x['total'])} ≠ مطبق {$f($x['a'])} + مسترد {$f($x['r'])} + رصيد {$f($x['sc'])}"];
});
$add('calc_purchases_credits', 'calc', 'warning', 'حسابات المشتريات وإشعارات الدائن',
    'بند الشراء = الكمية × السعر؛ إشعار الدائن يجب أن يُوزَّع بالكامل بين: مطبق على الفاتورة + مسترد نقداً + رصيد للعميل.', 'تصحيح المستند المذكور.', $items);

/* ===================== COSTING & MARGINS ===================== */
// unit_cost is stored per *sale* unit (InvoiceController::recordLineCosts multiplies by the stock factor).
// A line can show a loss for two different reasons, told apart by the line's own variant/product cost:
//  - genuine: the price is below what that variant costs (cost_price × stock factor)  → a pricing problem;
//  - FIFO mixing: the price covers the variant's own cost, but the COGS came from the product's shared
//    FIFO layers (inventory_cost_layers has no variant column), which may hold a dearer variant's units.
$genuine = []; $mixing = []; $mixingOld = []; $olderBatch = [];
// Variant-level cost layers went live here (docs/fifo-variant-cost-layers-plan.md); earlier lines are posted history.
if (!defined('FIFO_VARIANT_SINCE')) define('FIFO_VARIANT_SINCE', '2026-11-17 04:00:00');
$safe(function () use (&$genuine, &$mixing, &$mixingOld, &$olderBatch, $q, $f) {
    foreach ($q("SELECT i.invoice_number, i.created_at inv_at, it.product_variant_id, (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = it.product_id) n_variants, it.product_name, it.quantity, it.unit_price, it.unit_cost, it.unit_name,
            GREATEST(1, ROUND(COALESCE(it.unit_multiplier,1))) factor, COALESCE(NULLIF(pv.cost_price,0), p.cost_price) own_cost
        FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id LEFT JOIN products p ON p.id = it.product_id LEFT JOIN product_variants pv ON pv.id = it.product_variant_id
        WHERE i.status <> 'cancelled' AND it.unit_cost IS NOT NULL AND it.unit_cost > 0 AND it.unit_price + 0.0001 < it.unit_cost
        ORDER BY (it.unit_cost - it.unit_price) * it.quantity DESC") as $x) {
        $own = $x['own_cost'] === null ? null : (float) $x['own_cost'] * (int) $x['factor'];
        $label = "فاتورة {$x['invoice_number']} — {$x['product_name']}" . ($x['unit_name'] ? " ({$x['unit_name']})" : '');
        if ($own !== null && $own > 0 && (float) $x['unit_price'] + 0.0001 >= $own) {
            $row = ['table' => 'inventory_cost_layers', 'detail' => "{$label}: بيع {$f($x['unit_price'])} يغطي كلفة المتغير {$f($own)}، لكن الكلفة المصروفة {$f($x['unit_cost'])} من طبقة متغير آخر"];
            // A product with no variants has nothing to mix with: its dearer cost is an older, costlier
            // batch being consumed first, which is what FIFO does. Only variant lines can cross variants.
            if (empty($x['product_variant_id']) && (int) $x['n_variants'] === 0)
                $olderBatch[] = ['table' => 'inventory_cost_layers', 'detail' => "{$label}: بيع {$f($x['unit_price'])} يغطي كلفة الصنف الحالية {$f($own)}، لكن الكلفة المصروفة {$f($x['unit_cost'])} من دفعة أقدم أغلى (FIFO)"];
            elseif ($x['inv_at'] >= FIFO_VARIANT_SINCE) $mixing[] = $row; else $mixingOld[] = $row;
        } else {
            $genuine[] = ['table' => 'invoice_items', 'column' => 'unit_price', 'detail' => "{$label}: بيع {$f($x['unit_price'])} وكلفته " . ($own ? $f($own) : $f($x['unit_cost'])) . " — خسارة " . $f((($own ?: (float) $x['unit_cost']) - (float) $x['unit_price']) * (float) $x['quantity'])];
        }
    }
});
$items = $genuine;
$add('sold_below_cost', 'costing', 'info', 'مبيعات تمت بأقل من كلفة الصنف',
    'سعر البيع كان أقل من كلفة المتغير نفسه — خصم أو سعر قديم. مبيعات منتهية لا تُعدَّل، لكنها تكشف أصنافاً تحتاج مراجعة تسعيرها.', 'مراجعة سعر الأصناف المذكورة؛ لا يلزم تعديل الفواتير السابقة.', $items);
$items = $mixing;
$add('fifo_variant_mixing', 'costing', 'critical', 'كلفة مبيعات محمّلة من طبقة متغير آخر (بعد إصلاح FIFO حسب المتغير)',
    'منذ ' . FIFO_VARIANT_SINCE . ' تُستهلك طبقات الكلفة حسب المتغير؛ ظهور بند هنا يعني مساراً برمجياً لا يمرر المتغير (cost_variant_id) أو طبقة مشتركة قديمة.',
    'تمرير cost_variant_id في المسار الذي صرف البند، أو إسناد الطبقة: php artisan inventory:assign-layer-variants.', $items);
$items = $olderBatch;
$add('fifo_older_batch', 'costing', 'info', 'بنود صُرفت من دفعة أقدم بكلفة أعلى من كلفة الصنف الحالية',
    'صنف بلا متغيرات استهلك FIFO دفعة قديمة أغلى من آخر كلفة؛ هذا سلوك FIFO الصحيح وليس خللاً، لكن هامش هذه البنود يبدو أقل من هامش السعر الحالي.',
    'لا يلزم إجراء؛ راجع سعر البيع إن كانت الكلفة الحالية أقل بكثير.', $items);
$items = $mixingOld;
$add('fifo_variant_mixing_history', 'costing', 'info', 'بنود قديمة حُمّلت كلفة متغير آخر (قبل الإصلاح)',
    'بيعت قبل تفعيل طبقات الكلفة حسب المتغير. إجمالي الكلفة صحيح، وربحية البند وحده مشوّهة. قيود فترات مرحّلة لا يُعاد احتسابها.', 'لا يلزم إجراء.', $items);

$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT l.product_id, p.name_ar, COUNT(*) n, SUM(l.remaining_quantity) rem FROM inventory_cost_layers l JOIN products p ON p.id = l.product_id
        WHERE l.product_variant_id IS NULL AND l.remaining_quantity > 0
        -- only products whose variants actually differ in cost: if they all cost the same, a shared layer costs every variant correctly
        AND (SELECT MAX(v.cost_price) - MIN(v.cost_price) FROM product_variants v WHERE v.product_id = l.product_id AND v.cost_price > 0) > 0.01 * (SELECT MIN(v.cost_price) FROM product_variants v WHERE v.product_id = l.product_id AND v.cost_price > 0)
        GROUP BY l.product_id, p.name_ar ORDER BY rem DESC") as $x)
        $items[] = ['table' => 'inventory_cost_layers', 'column' => 'product_variant_id', 'detail' => "منتج #{$x['product_id']} {$x['name_ar']}: {$x['n']} طبقة مشتركة ({$f($x['rem'])} وحدة) بلا متغير"];
});
$add('shared_layers', 'costing', 'info', 'طبقات كلفة مشتركة لمنتجات لها متغيرات',
    'طبقات سابقة لتتبع المتغير لم يُعرف متغيرها بيقين؛ تُستهلك بعد طبقات المتغير نفسه، ويتناقص عددها مع البيع.', 'لا يلزم إجراء؛ أو أسندها يدوياً إن عُرف متغيرها.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    $r = $q("SELECT COUNT(*) n, COUNT(DISTINCT it.invoice_id) inv, MIN(i.created_at) a, MAX(i.created_at) b FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id
        WHERE i.status <> 'cancelled' AND it.unit_cost IS NULL")[0];
    if ((int) $r['n']) $items[] = ['table' => 'invoice_items', 'column' => 'unit_cost', 'detail' => "{$r['n']} بند في {$r['inv']} فاتورة بلا كلفة مسجلة (من " . substr($r['a'], 0, 10) . ' إلى ' . substr($r['b'], 0, 10) . ')'];
    foreach ($q("SELECT i.invoice_number, it.product_name FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id JOIN products p ON p.id = it.product_id
        WHERE i.status <> 'cancelled' AND it.unit_cost = 0 AND p.cost_price > 0 LIMIT 50") as $x)
        $items[] = ['table' => 'invoice_items', 'column' => 'unit_cost', 'detail' => "فاتورة {$x['invoice_number']} — {$x['product_name']}: كلفة 0 والمنتج له كلفة"];
});
$add('missing_cost', 'costing', 'info', 'بنود مبيعات بلا كلفة مسجلة',
    'تقارير الربحية والهامش لهذه البنود غير موثوقة؛ غالباً فواتير سابقة لبدء تسجيل الكلفة على البند.', 'لا يلزم إجراء للفواتير القديمة؛ تأكد أن الفواتير الجديدة تسجل الكلفة (تُسجَّل تلقائياً منذ تفعيل الميزة).', $items);

// Products with variants carry min/aggregate figures at product level, so compare per variant; simple products per product.
$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT p.id, p.name_ar, p.price, p.cost_price FROM products p WHERE p.is_active = 1 AND p.price > 0 AND p.cost_price > 0 AND p.price < p.cost_price
        AND NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id) ORDER BY (p.cost_price - p.price) DESC") as $x)
        $items[] = ['table' => 'products', 'column' => 'price', 'detail' => "منتج #{$x['id']} {$x['name_ar']}: سعر {$f($x['price'])} < كلفة {$f($x['cost_price'])}"];
    foreach ($q("SELECT pv.id, p.name_ar, CONCAT_WS(' ', pv.size, pv.color, pv.material) v, pv.price, pv.cost_price FROM product_variants pv JOIN products p ON p.id = pv.product_id
        WHERE p.is_active = 1 AND pv.price > 0 AND pv.cost_price > 0 AND pv.price < pv.cost_price ORDER BY (pv.cost_price - pv.price) DESC") as $x)
        $items[] = ['table' => 'product_variants', 'column' => 'price', 'detail' => "متغير #{$x['id']} {$x['name_ar']} [{$x['v']}]: سعر {$f($x['price'])} < كلفة {$f($x['cost_price'])}"];
});
$add('catalog_below_cost', 'costing', 'warning', 'منتجات سعرها في الكتالوج أقل من كلفتها',
    'كل بيع بالسعر الحالي خاسر؛ الأرجح أن الكلفة ارتفعت مع آخر استلام ولم يُحدَّث سعر البيع.', 'تحديث سعر البيع (أو مراجعة الكلفة إن كانت خاطئة).', $items);

$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT l.product_id, l.warehouse_id, l.rem, COALESCE(w.qty,0) qty, p.name_ar FROM
        (SELECT product_id, warehouse_id, SUM(remaining_quantity) rem FROM inventory_cost_layers GROUP BY product_id, warehouse_id) l
        LEFT JOIN (SELECT product_id, warehouse_id, SUM(quantity) qty FROM warehouse_inventory GROUP BY product_id, warehouse_id) w ON w.product_id = l.product_id AND w.warehouse_id = l.warehouse_id
        LEFT JOIN products p ON p.id = l.product_id
        WHERE ABS(l.rem - COALESCE(w.qty,0)) > 0.001 ORDER BY ABS(l.rem - COALESCE(w.qty,0)) DESC") as $x)
        $items[] = ['table' => 'inventory_cost_layers', 'column' => 'remaining_quantity', 'detail' => "منتج #{$x['product_id']} {$x['name_ar']} / مستودع #{$x['warehouse_id']}: طبقات الكلفة {$f($x['rem'])} ≠ الرصيد {$f($x['qty'])}"];
    foreach ($q("SELECT w.product_id, w.warehouse_id, SUM(w.quantity) qty, p.name_ar FROM warehouse_inventory w LEFT JOIN products p ON p.id = w.product_id
        WHERE NOT EXISTS (SELECT 1 FROM inventory_cost_layers l WHERE l.product_id = w.product_id AND l.warehouse_id = w.warehouse_id)
        GROUP BY w.product_id, w.warehouse_id, p.name_ar HAVING qty > 0") as $x)
        $items[] = ['table' => 'inventory_cost_layers', 'detail' => "منتج #{$x['product_id']} {$x['name_ar']} / مستودع #{$x['warehouse_id']}: رصيد {$f($x['qty'])} بلا أي طبقة كلفة"];
});
$add('fifo_layers', 'costing', 'warning', 'طبقات كلفة FIFO لا تطابق الرصيد',
    'كلفة كل صرف تُسحب من طبقات الكلفة؛ إن كانت الطبقات أقل من الرصيد تُصرف وحدات بكلفة 0 أو بكلفة احتياطية، وإن كانت أكثر تُحمَّل المبيعات كلفة بضاعة غير موجودة.', 'إنشاء طبقة افتتاحية بالفرق بكلفة المنتج، أو استهلاك الطبقات الزائدة، عبر تسوية مخزون.', $items);

// Open layers valued at 0: whatever is sold from them carries no cost of goods (inflated profit).
$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT COALESCE(l.source,'—') src, l.warehouse_id, COUNT(*) n, SUM(l.remaining_quantity) units,
            SUM(l.remaining_quantity * COALESCE(NULLIF(v.cost_price,0), p.cost_price, 0)) worth
        FROM inventory_cost_layers l JOIN products p ON p.id = l.product_id LEFT JOIN product_variants v ON v.id = l.product_variant_id
        WHERE l.remaining_quantity > 0 AND l.unit_cost = 0 GROUP BY src, l.warehouse_id ORDER BY worth DESC") as $x)
        $items[] = ['table' => 'inventory_cost_layers', 'column' => 'unit_cost', 'detail' => "مستودع #{$x['warehouse_id']} — مصدر {$x['src']}: {$x['n']} طبقة، {$f($x['units'])} وحدة بكلفة 0 (قيمتها بكلفة الصنف ≈ {$f($x['worth'])})"];
});
$add('zero_cost_layers', 'costing', 'warning', 'طبقات كلفة مفتوحة بكلفة صفر',
    'ما يُباع منها يُسجَّل بلا كلفة بضاعة مباعة فيظهر ربحاً كاملاً، والمخزون مقوَّم بأقل من قيمته. مصدرها الأساسي تحويلات قديمة فتحت طبقة الوجهة بكلفة 0 (خلل أُصلح لاحقاً).',
    'إعادة تقويم الطبقة بكلفة الحركة الصادرة المقابلة لها مع قيد تسوية — يحتاج اعتماداً محاسبياً.', $items);

// The ledger's inventory account per warehouse must equal what the cost layers say is there.
$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT w.id, w.name, COALESCE(lg.bal,0) ledger, COALESCE(ly.val,0) layers FROM warehouses w
        LEFT JOIN (SELECT a.warehouse_id, SUM(jl.debit) - SUM(jl.credit) bal FROM journal_entry_lines jl JOIN journal_entry_headers h ON h.id = jl.journal_entry_header_id AND h.deleted_at IS NULL
            JOIN ledger_accounts a ON a.id = jl.account_id WHERE a.warehouse_id IS NOT NULL AND a.type = 'asset' GROUP BY a.warehouse_id) lg ON lg.warehouse_id = w.id
        LEFT JOIN (SELECT warehouse_id, SUM(remaining_quantity * unit_cost) val FROM inventory_cost_layers WHERE remaining_quantity > 0 GROUP BY warehouse_id) ly ON ly.warehouse_id = w.id
        WHERE ABS(COALESCE(lg.bal,0) - COALESCE(ly.val,0)) > 1") as $x)
        $items[] = ['table' => 'journal_entry_lines', 'detail' => "مستودع #{$x['id']} {$x['name']}: حساب المخزون في الدفاتر {$f($x['ledger'])} وقيمة طبقات الكلفة {$f($x['layers'])} — فرق " . $f($x['ledger'] - $x['layers'])];
});
$add('ledger_vs_layers', 'costing', 'critical', 'قيمة المخزون في الدفاتر لا تطابق طبقات الكلفة (لكل مستودع)',
    'حساب مخزون كل مستودع يجب أن يساوي قيمة طبقات الكلفة فيه. الفرق يأتي من حركات لم تُرحَّل محاسبياً: تحويلات بين المستودعات، رصيد افتتاحي مستورد، ومبيعات صُرفت من طبقات بكلفة 0. فحص النظام الليلي لا يقارن بينهما.',
    'تسوية محاسبية مُعتمدة: قيد التحويل بين حسابي المستودعين، قيد الرصيد الافتتاحي، وتصحيح كلفة المبيعات.', $items);

/* ===================== STATES & TIMELINE ===================== */
$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT id, order_number, status, confirmed_at, shipped_at, delivered_at FROM sales_orders WHERE
        (confirmed_at IS NOT NULL AND shipped_at IS NOT NULL AND shipped_at < confirmed_at) OR (shipped_at IS NOT NULL AND delivered_at IS NOT NULL AND delivered_at < shipped_at)") as $x)
        $items[] = ['table' => 'sales_orders', 'detail' => "أمر {$x['order_number']}: تسلسل زمني معكوس (تأكيد {$x['confirmed_at']}، شحن {$x['shipped_at']}، تسليم {$x['delivered_at']})"];
    foreach ($q("SELECT id, order_number, status FROM sales_orders WHERE (status = 'delivered' AND delivered_at IS NULL) OR (status IN ('shipped','delivered') AND shipped_at IS NULL AND delivered_at IS NULL)
        OR (status IN ('confirmed','processing','shipped','delivered') AND confirmed_at IS NULL)") as $x)
        $items[] = ['table' => 'sales_orders', 'detail' => "أمر {$x['order_number']} حالته {$x['status']} وتاريخ المرحلة غير مسجل"];
    foreach ($q("SELECT s.order_number, s.status so_st, i.invoice_number, i.status inv_st FROM sales_orders s JOIN invoices i ON i.sales_order_id = s.id
        WHERE (s.status = 'cancelled' AND i.status <> 'cancelled') OR (s.status <> 'cancelled' AND i.status = 'cancelled' AND NOT EXISTS (SELECT 1 FROM invoices i2 WHERE i2.sales_order_id = s.id AND i2.status <> 'cancelled'))") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'status', 'detail' => "أمر {$x['order_number']} ({$x['so_st']}) وفاتورته {$x['invoice_number']} ({$x['inv_st']}) — حالتان متناقضتان"];
});
$add('state_timeline', 'state', 'critical', 'تناقض في حالات المستندات وتواريخها',
    'آلة الحالات تسجل تاريخ كل مرحلة، وأمر البيع وفاتورته يُلغيان معاً؛ التناقض يعني تعديلاً خارج مسار العمل.', 'مراجعة المستند وتصحيح حالته عبر شاشة الطلب.', $items);

$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT id, invoice_number, paid_at, due_amount FROM invoices WHERE status <> 'cancelled' AND paid_at IS NOT NULL AND due_amount > 0.01") as $x)
        $items[] = ['table' => 'invoices', 'column' => 'paid_at', 'detail' => "فاتورة {$x['invoice_number']}: مسجّل لها تاريخ سداد ومتبقي عليها {$f($x['due_amount'])}"];
    foreach ($q("SELECT p.payment_number, p.payment_date, i.invoice_number, DATE(i.created_at) inv_date FROM payments p JOIN invoices i ON i.id = p.invoice_id WHERE p.payment_date < DATE(i.created_at)") as $x)
        $items[] = ['table' => 'payments', 'column' => 'payment_date', 'detail' => "دفعة {$x['payment_number']} بتاريخ {$x['payment_date']} قبل فاتورتها {$x['invoice_number']} ({$x['inv_date']})"];
    foreach ($q("SELECT id, rma_number, status FROM rma_requests WHERE deleted_at IS NULL AND status = 'completed' AND (completed_at IS NULL OR (approved_at IS NOT NULL AND completed_at < approved_at))") as $x)
        $items[] = ['table' => 'rma_requests', 'detail' => "طلب إرجاع {$x['rma_number']}: مكتمل بلا تاريخ إكمال صحيح"];
});
$add('state_payments', 'state', 'warning', 'تناقض في حالات السداد والإرجاع', 'تاريخ السداد يعني أن الفاتورة سُددت بالكامل؛ والدفعة لا تسبق فاتورتها.', 'تصحيح التاريخ أو المبلغ المتبقي.', $items);

/* ===================== RESERVATIONS ===================== */
// Expected reservation per product/warehouse, mirroring SalesOrderWorkflowService::lineSourcesFor:
// each line of a confirmed/processing order reserves its allocations (if any) and the remainder at the
// order's fulfilment warehouse, in stock units (× max(1, round(unit_multiplier))). Pending transfers
// reserve at their source warehouse (InventoryTransferController).
$items = [];
$safe(function () use (&$items, $q, $f) {
    $expected = [];
    $lines = $q("SELECT si.id, si.product_id, si.quantity, GREATEST(1, ROUND(COALESCE(si.unit_multiplier,1))) factor, s.fulfillment_warehouse_id wh
        FROM sales_order_items si JOIN sales_orders s ON s.id = si.sales_order_id WHERE s.status IN ('confirmed','processing')");
    $allocs = [];
    foreach ($q("SELECT al.sales_order_item_id id, al.warehouse_id wh, SUM(al.quantity) qty FROM sales_order_item_allocations al JOIN sales_order_items si ON si.id = al.sales_order_item_id
        JOIN sales_orders s ON s.id = si.sales_order_id WHERE s.status IN ('confirmed','processing') AND al.quantity > 0 GROUP BY al.sales_order_item_id, al.warehouse_id") as $x)
        $allocs[$x['id']][$x['wh']] = (int) $x['qty'];
    foreach ($lines as $l) {
        $src = $allocs[$l['id']] ?? [];
        $short = (int) $l['quantity'] - array_sum($src);
        if ($short > 0) $src[(int) $l['wh']] = ($src[(int) $l['wh']] ?? 0) + $short;
        foreach ($src as $wh => $qty) { $k = $l['product_id'] . ':' . $wh; $expected[$k] = ($expected[$k] ?? 0) + $qty * (int) $l['factor']; }
    }
    foreach ($q("SELECT ti.product_id, t.from_warehouse_id wh, SUM(ti.quantity_requested) qty FROM inventory_transfer_items ti JOIN inventory_transfers t ON t.id = ti.transfer_id
        WHERE t.status NOT IN ('completed','cancelled','received','shipped','in_transit') GROUP BY ti.product_id, t.from_warehouse_id") as $x) {
        $k = $x['product_id'] . ':' . $x['wh']; $expected[$k] = ($expected[$k] ?? 0) + (int) $x['qty'];
    }
    $actual = [];
    $names = [];
    foreach ($q("SELECT w.product_id, w.warehouse_id, SUM(w.reserved_quantity) r, MAX(p.name_ar) n FROM warehouse_inventory w LEFT JOIN products p ON p.id = w.product_id GROUP BY w.product_id, w.warehouse_id HAVING r <> 0") as $x) {
        $k = $x['product_id'] . ':' . $x['warehouse_id']; $actual[$k] = (float) $x['r']; $names[$k] = $x['n'];
    }
    foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $k) {
        $e = $expected[$k] ?? 0; $a = $actual[$k] ?? 0;
        if (abs($e - $a) < 0.001) continue;
        [$pid, $wh] = explode(':', $k);
        $items[] = ['table' => 'warehouse_inventory', 'column' => 'reserved_quantity', 'detail' => "منتج #{$pid} " . ($names[$k] ?? '') . " / مستودع #{$wh}: محجوز {$f($a)} والمتوقع من الأوامر والتحويلات المفتوحة {$f($e)}"];
    }
});
$add('reservations', 'reservation', 'warning', 'الحجوزات لا تطابق الأوامر والتحويلات المفتوحة',
    'التأكيد يحجز المخزون (حسب توزيع البند أو مستودع التنفيذ) والشحن أو الإلغاء يحرره؛ حجز زائد يُخفي مخزوناً متاحاً للبيع، وحجز ناقص قد يُباع مخزونه لغيره.', 'تحرير الحجز اليتيم من شاشة المخزون، أو إعادة تأكيد الأمر لإعادة الحجز.', $items);

/* ===================== CREDIT ===================== */
$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT id, name, balance, credit_limit FROM customers WHERE credit_limit > 0 AND balance > credit_limit + 0.01 ORDER BY balance - credit_limit DESC") as $x)
        $items[] = ['table' => 'customers', 'column' => 'balance', 'detail' => "عميل #{$x['id']} {$x['name']}: رصيد {$f($x['balance'])} يتجاوز الحد الائتماني {$f($x['credit_limit'])}"];
    foreach ($q("SELECT id, name, balance, credit_limit FROM suppliers WHERE credit_limit > 0 AND balance > credit_limit + 0.01") as $x)
        $items[] = ['table' => 'suppliers', 'column' => 'balance', 'detail' => "مورد #{$x['id']} {$x['name']}: مستحق له {$f($x['balance'])} يتجاوز الحد {$f($x['credit_limit'])}"];
});
$add('credit_limits', 'credit', 'warning', 'تجاوز الحدود الائتمانية', 'البيع الآجل فوق الحد يرفع مخاطر عدم التحصيل.', 'إيقاف البيع الآجل للعميل حتى السداد أو رفع الحد بقرار إداري.', $items);

$items = [];
$safe(function () use (&$items, $q, $f) {
    foreach ($q("SELECT id, name, balance FROM customers WHERE balance < -0.01 ORDER BY balance") as $x)
        $items[] = ['table' => 'customers', 'column' => 'balance', 'detail' => "عميل #{$x['id']} {$x['name']}: رصيد دائن {$f(-$x['balance'])} (له لا عليه)"];
    foreach ($q("SELECT id, name, balance FROM suppliers WHERE balance < -0.01 ORDER BY balance") as $x)
        $items[] = ['table' => 'suppliers', 'column' => 'balance', 'detail' => "مورد #{$x['id']} {$x['name']}: رصيد مدين {$f(-$x['balance'])} (دفعنا أكثر مما استلمنا)"];
});
$add('credit_balances', 'credit', 'info', 'أرصدة معكوسة (دفعات مقدمة أو زائدة)', 'عميل له رصيد أو مورد دُفع له مقدماً؛ طبيعي إن كان مقصوداً، ويستحق المتابعة إن لم يكن.', 'تسويته مع المشتريات/المبيعات القادمة أو رده.', $items);

$CATS = ['calc' => 'المعادلات والحسابات', 'costing' => 'الكلفة والهوامش', 'state' => 'الحالات والتسلسل الزمني', 'reservation' => 'حجز المخزون', 'credit' => 'الائتمان والأرصدة'];
$w = ['critical' => 12, 'warning' => 5, 'info' => 1, 'ok' => 0, 'error' => 0];
$score = max(0, 100 - array_sum(array_map(fn ($c) => $w[$c['sev']] * min(3, max(1, (int) ceil($c['count'] / 5))), $checks)));

file_put_contents(getenv('LOGIC_OUT'), json_encode(['generated_at' => now()->format('Y-m-d H:i'), 'score' => $score, 'cats' => $CATS, 'checks' => $checks], JSON_UNESCAPED_UNICODE));
echo 'ok';
