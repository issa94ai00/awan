<?php
/*
 * Read-only business-workflow diagnostics (sales, purchasing, returns, accounting links).
 * Run through tinker (see build.sh); writes JSON to $WF_OUT. SELECT queries only.
 *
 * Rules mirror the app's own posting logic:
 *  - Confirming a sales order creates its invoice + revenue entry (SalesOrderWorkflowService::applyConfirmation).
 *  - Shipping a sales order issues stock as reference='sales_order', source=<order id>.
 *  - A direct invoice issues stock as reference='invoice', source=<invoice id> and posts revenue/COGS
 *    (InvoiceController) — failures there are caught and logged, so gaps can be silent.
 *  - Payments / supplier payments / receipts / returns / credit notes each post one journal entry
 *    (journal_entry_headers.reference_type = the model class).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$q = fn (string $sql, array $b = []) => array_map(fn ($r) => (array) $r, DB::select($sql, $b));
$one = fn (string $sql, array $b = []) => (array) DB::selectOne($sql, $b);
$has = fn (string $t) => Schema::hasTable($t);

$checks = [];
$errors = [];
$safe = function (callable $fn, $fallback = null) use (&$errors) { try { return $fn(); } catch (\Throwable $e) { $errors[] = $e->getMessage(); return $fallback; } };
$add = function (string $id, string $flow, string $sev, string $title, string $why, string $fix, array $items) use (&$checks, &$errors) {
    $err = $errors; $errors = [];
    $checks[] = ['id' => $id, 'cat' => $flow, 'sev' => $err ? 'error' : ($items ? $sev : 'ok'), 'error' => $err ? mb_substr(implode(' | ', array_unique($err)), 0, 300) : null,
        'title' => $title, 'why' => $why, 'fix' => $fix, 'count' => count($items), 'items' => array_slice($items, 0, 200)];
};
$je = fn (string $model) => "SELECT 1 FROM journal_entry_headers j WHERE j.reference_type = 'App\\\\Models\\\\{$model}' AND j.deleted_at IS NULL";
$num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
$days = fn ($v) => $v === null ? null : round((float) $v, 1);

/* ================= flow snapshots ================= */
$status = fn (string $t) => $safe(fn () => array_column($q("SELECT status s, COUNT(*) n FROM `{$t}` GROUP BY status ORDER BY n DESC"), 'n', 's'), []);
$cnt = fn (string $sql) => (int) ($safe(fn () => $one($sql)['n'], 0));

$flows = [];

// ---- sales
$qt = $status('quotes'); $so = $status('sales_orders'); $inv = $status('invoices'); $pay = $status('payments');
$quotesToSo = $cnt('SELECT COUNT(DISTINCT quote_id) n FROM sales_orders WHERE quote_id IS NOT NULL');
$soInvoiced = $cnt('SELECT COUNT(DISTINCT sales_order_id) n FROM invoices WHERE sales_order_id IS NOT NULL');
$directInv = $cnt("SELECT COUNT(*) n FROM invoices WHERE sales_order_id IS NULL AND status <> 'cancelled'");
$invPaid = $cnt("SELECT COUNT(*) n FROM invoices WHERE status <> 'cancelled' AND due_amount <= 0.01");
$invOpen = $cnt("SELECT COUNT(*) n FROM invoices WHERE status <> 'cancelled' AND due_amount > 0.01");
$receivable = (float) ($safe(fn () => $one("SELECT COALESCE(SUM(due_amount),0) n FROM invoices WHERE status <> 'cancelled' AND due_amount > 0.01")['n'], 0));
$salesTotal = (float) ($safe(fn () => $one("SELECT COALESCE(SUM(total),0) n FROM invoices WHERE status <> 'cancelled'")['n'], 0));
$cyc = $safe(fn () => $one("SELECT
    (SELECT AVG(TIMESTAMPDIFF(HOUR, qq.created_at, s.created_at))/24 FROM sales_orders s JOIN quotes qq ON qq.id = s.quote_id) quote_to_so,
    (SELECT AVG(TIMESTAMPDIFF(HOUR, s.created_at, s.delivered_at))/24 FROM sales_orders s WHERE s.delivered_at IS NOT NULL) so_to_delivery,
    (SELECT AVG(TIMESTAMPDIFF(HOUR, i.created_at, p.last_paid))/24 FROM invoices i JOIN (SELECT invoice_id, MAX(created_at) last_paid FROM payments WHERE status='completed' GROUP BY invoice_id) p ON p.invoice_id = i.id
        WHERE i.status <> 'cancelled' AND i.due_amount <= 0.01) invoice_to_paid,
    (SELECT AVG(TIMESTAMPDIFF(HOUR, i.created_at, p.first_paid))/24 FROM invoices i JOIN (SELECT invoice_id, MIN(created_at) first_paid FROM payments WHERE status='completed' GROUP BY invoice_id) p ON p.invoice_id = i.id
        WHERE i.status <> 'cancelled') invoice_to_first_payment"), []);
$flows[] = [
    'id' => 'sales', 'label' => 'دورة المبيعات',
    'stages' => [
        ['key' => 'quotes', 'label' => 'عروض الأسعار', 'table' => 'quotes', 'count' => array_sum($qt), 'statuses' => $qt],
        ['key' => 'sales_orders', 'label' => 'أوامر البيع', 'table' => 'sales_orders', 'count' => array_sum($so), 'statuses' => $so],
        ['key' => 'invoices', 'label' => 'الفواتير', 'table' => 'invoices', 'count' => array_sum($inv), 'statuses' => $inv],
        ['key' => 'payments', 'label' => 'التحصيل', 'table' => 'payments', 'count' => array_sum($pay), 'statuses' => $pay],
    ],
    'links' => [
        ['label' => 'عروض تحولت لأوامر بيع', 'value' => $quotesToSo, 'of' => array_sum($qt)],
        ['label' => 'أوامر بيع لها فاتورة', 'value' => $soInvoiced, 'of' => array_sum($so) - ($so['cancelled'] ?? 0)],
        ['label' => 'فواتير مسددة بالكامل', 'value' => $invPaid, 'of' => $invPaid + $invOpen],
    ],
    'metrics' => [
        ['label' => 'فواتير مباشرة (بدون أمر بيع)', 'value' => $directInv],
        ['label' => 'إجمالي المبيعات (غير الملغاة)', 'value' => $num($salesTotal)],
        ['label' => 'ذمم العملاء المفتوحة', 'value' => $num($receivable) . " ({$invOpen} فاتورة)"],
        ['label' => 'متوسط: عرض ← أمر بيع', 'value' => $days($cyc['quote_to_so'] ?? null), 'unit' => 'يوم'],
        ['label' => 'متوسط: أمر بيع ← تسليم', 'value' => $days($cyc['so_to_delivery'] ?? null), 'unit' => 'يوم'],
        ['label' => 'متوسط: فاتورة ← أول دفعة', 'value' => $days($cyc['invoice_to_first_payment'] ?? null), 'unit' => 'يوم'],
        ['label' => 'متوسط: فاتورة ← سداد كامل', 'value' => $days($cyc['invoice_to_paid'] ?? null), 'unit' => 'يوم'],
    ],
];

// ---- purchasing
$po = $status('purchase_orders'); $pr = $status('purchase_receipts'); $sp = $status('supplier_payments'); $pret = $status('purchase_returns');
$poReceived = $cnt('SELECT COUNT(DISTINCT purchase_order_id) n FROM purchase_receipts WHERE purchase_order_id IS NOT NULL');
// purchase_orders.paid_amount/due_amount are not maintained by the app; the supplier balance is.
$payable = (float) ($safe(fn () => $one("SELECT COALESCE(SUM(balance),0) n FROM suppliers WHERE balance > 0.01")['n'], 0));
$poOpenPay = $cnt("SELECT COUNT(*) n FROM suppliers WHERE balance > 0.01");
$pcyc = $safe(fn () => $one("SELECT
    (SELECT AVG(TIMESTAMPDIFF(HOUR, p.created_at, r.first_rcv))/24 FROM purchase_orders p JOIN (SELECT purchase_order_id, MIN(created_at) first_rcv FROM purchase_receipts GROUP BY purchase_order_id) r ON r.purchase_order_id = p.id) po_to_receipt"), []);
$flows[] = [
    'id' => 'purchasing', 'label' => 'دورة المشتريات',
    'stages' => [
        ['key' => 'purchase_orders', 'label' => 'أوامر الشراء', 'table' => 'purchase_orders', 'count' => array_sum($po), 'statuses' => $po],
        ['key' => 'purchase_receipts', 'label' => 'إيصالات الاستلام', 'table' => 'purchase_receipts', 'count' => array_sum($pr), 'statuses' => $pr],
        ['key' => 'supplier_payments', 'label' => 'مدفوعات الموردين', 'table' => 'supplier_payments', 'count' => array_sum($sp), 'statuses' => $sp],
        ['key' => 'purchase_returns', 'label' => 'مرتجعات المشتريات', 'table' => 'purchase_returns', 'count' => array_sum($pret), 'statuses' => $pret],
    ],
    'links' => [
        ['label' => 'أوامر شراء استُلمت (كلياً أو جزئياً)', 'value' => $poReceived, 'of' => array_sum($po) - ($po['cancelled'] ?? 0)],
    ],
    'metrics' => [
        ['label' => 'ذمم الموردين المستحقة (أرصدة الموردين)', 'value' => $num($payable) . " ({$poOpenPay} مورد)"],
        ['label' => 'متوسط: أمر شراء ← أول استلام', 'value' => $days($pcyc['po_to_receipt'] ?? null), 'unit' => 'يوم'],
    ],
];

// ---- returns
$rma = $status('rma_requests'); $cn = $status('credit_notes');
$flows[] = [
    'id' => 'returns', 'label' => 'المرتجعات وما بعد البيع',
    'stages' => [
        ['key' => 'rma_requests', 'label' => 'طلبات الإرجاع', 'table' => 'rma_requests', 'count' => array_sum($rma), 'statuses' => $rma],
        ['key' => 'credit_notes', 'label' => 'إشعارات الدائن', 'table' => 'credit_notes', 'count' => array_sum($cn), 'statuses' => $cn],
        ['key' => 'refunds', 'label' => 'استردادات نقدية', 'table' => 'payments', 'count' => (int) ($pay['refunded'] ?? 0), 'statuses' => ['refunded' => (int) ($pay['refunded'] ?? 0)]],
    ],
    'links' => [['label' => 'طلبات إرجاع مكتملة', 'value' => (int) ($rma['completed'] ?? 0), 'of' => array_sum($rma) - ($rma['rejected'] ?? 0) - ($rma['cancelled'] ?? 0)]],
    'metrics' => [],
];

// ---- accounting / inventory backbone
$jeByType = $safe(fn () => array_column($q("SELECT COALESCE(NULLIF(REPLACE(reference_type,'App\\\\Models\\\\',''),''),'يدوي') s, COUNT(*) n FROM journal_entry_headers WHERE deleted_at IS NULL GROUP BY s ORDER BY n DESC"), 'n', 's'), []);
$smByRef = $safe(fn () => array_column($q("SELECT CASE WHEN reference IN ('invoice','sales_order','sales_order_cancelled') THEN reference
        WHEN source IN ('purchase_receipt','purchase_return','opening_stock','transfer','rma','stock_count','merge','sales') THEN source
        WHEN reference LIKE 'PR-%' OR reference LIKE 'PRT-%' THEN 'purchase'
        WHEN COALESCE(reference,'') = '' AND COALESCE(source,'') = '' THEN 'غير معروف'
        ELSE 'أخرى' END s, COUNT(*) n FROM stock_movements GROUP BY s ORDER BY n DESC"), 'n', 's'), []);
$flows[] = [
    'id' => 'ledger', 'label' => 'الترحيل المحاسبي والمخزني',
    'stages' => [
        ['key' => 'journal', 'label' => 'القيود اليومية حسب المصدر', 'table' => 'journal_entry_headers', 'count' => array_sum($jeByType), 'statuses' => $jeByType],
        ['key' => 'stock', 'label' => 'حركات المخزون حسب المصدر', 'table' => 'stock_movements', 'count' => array_sum($smByRef), 'statuses' => $smByRef],
    ],
    'links' => [], 'metrics' => [],
];

/* ================= SALES checks ================= */
$items = [];
$safe(function () use (&$items, $q, $safe) {
    foreach ($q("SELECT s.id, s.order_number, DATEDIFF(NOW(), s.created_at) age, s.total, c.name cust,
            (SELECT COUNT(*) FROM sales_order_items i WHERE i.sales_order_id = s.id) ln,
            (SELECT GROUP_CONCAT(CONCAT(p.order_number,' (',p.status,')') SEPARATOR '، ') FROM purchase_orders p WHERE p.sales_order_id = s.id AND p.status NOT IN ('completed','cancelled')) pos
        FROM sales_orders s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.status = 'pending' AND s.created_at < NOW() - INTERVAL 7 DAY ORDER BY s.created_at") as $x) {
        // The app's own shortage calculation (the one that gates confirmation), not an estimate.
        $short = $safe(fn () => app(\App\Services\Sales\SalesOrderWorkflowService::class)->stockShortages(\App\Models\SalesOrder::find($x['id'])), null);
        $cov = $short === null ? 'تعذّر حساب التوفر' : ($short === [] ? 'المخزون يغطي الأمر — جاهز للتأكيد' : 'ينقصه ' . count($short) . " من {$x['ln']} بنود");
        $items[] = ['table' => 'sales_orders', 'detail' => "أمر {$x['order_number']} (#{$x['id']}) — {$x['cust']} — " . (float) $x['total'] . " — معلّق منذ {$x['age']} يوماً — {$cov}" . ($x['pos'] ? " — بانتظار {$x['pos']}" : '')];
    }
});
$add('so_stuck_pending', 'sales', 'warning', 'أوامر بيع معلّقة لأكثر من 7 أيام',
    'الأمر المعلّق لا يحجز مخزوناً ولا يُنشئ فاتورة ولا يدخل الذمم؛ تراكمها يعني طلبات منسية أو مسودات لم تُحذف.',
    'مراجعة كل أمر: تأكيده (يُنشئ الفاتورة ويحجز المخزون) أو إلغاؤه.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT s.id, s.order_number, s.status FROM sales_orders s LEFT JOIN invoices i ON i.sales_order_id = s.id AND i.status <> 'cancelled'
        WHERE s.status IN ('confirmed','processing','shipped','delivered') AND i.id IS NULL") as $x)
        $items[] = ['table' => 'sales_orders', 'detail' => "أمر {$x['order_number']} (#{$x['id']}) حالته {$x['status']} بدون فاتورة سارية"];
});
$add('so_without_invoice', 'sales', 'critical', 'أوامر بيع مؤكدة/مسلّمة بدون فاتورة',
    'تأكيد الأمر يُنشئ فاتورته تلقائياً؛ غيابها يعني إيراداً وذمماً غير مسجلة.', 'إعادة تأكيد الأمر أو إنشاء الفاتورة من شاشة الأمر.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT s.id, s.order_number, s.status FROM sales_orders s
        WHERE s.status IN ('shipped','delivered') AND NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.reference = 'sales_order' AND m.source = s.id)") as $x)
        $items[] = ['table' => 'sales_orders', 'detail' => "أمر {$x['order_number']} (#{$x['id']}) حالته {$x['status']} ولم تُسجَّل له حركة صرف مخزون"];
});
$add('so_shipped_no_stock', 'sales', 'critical', 'أوامر بيع شُحنت دون صرف مخزون',
    'الشحن يجب أن يُنقص المخزون؛ غياب الحركة يعني رصيداً أعلى من الواقع وكلفة مبيعات غير مسجلة.', 'إعادة تنفيذ الشحن أو تسجيل حركة صرف مع قيد كلفة البضاعة.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT i.id, i.invoice_number, i.total FROM invoices i WHERE i.status <> 'cancelled' AND i.total > 0 AND NOT EXISTS ({$je('Invoice')} AND j.reference_id = i.id)") as $x)
        $items[] = ['table' => 'invoices', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}) بقيمة " . (float) $x['total'] . ' بدون قيد محاسبي'];
});
$add('invoice_no_journal', 'sales', 'critical', 'فواتير بدون قيد إيراد',
    'فشل الترحيل عند إنشاء الفاتورة يُسجَّل في السجل فقط ولا يوقف العملية، فتبقى الفاتورة خارج الدفاتر.', 'php artisan accounting:backfill (بوضع المعاينة أولاً) لترحيل الفواتير الناقصة.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT i.id, i.invoice_number FROM invoices i WHERE i.sales_order_id IS NULL AND i.status <> 'cancelled'
        AND EXISTS (SELECT 1 FROM invoice_items it JOIN products p ON p.id = it.product_id WHERE it.invoice_id = i.id)
        AND NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.reference = 'invoice' AND m.source = i.id)") as $x)
        $items[] = ['table' => 'invoices', 'detail' => "فاتورة مباشرة {$x['invoice_number']} (#{$x['id']}) لم تصرف أي مخزون"];
});
$add('direct_invoice_no_stock', 'sales', 'critical', 'فواتير مباشرة لم تُنقص المخزون',
    'الفاتورة المباشرة (بدون أمر بيع) تصرف المخزون بنفسها؛ إن فشل الصرف تُحفظ الفاتورة مع تحذير فقط.', 'تسجيل حركة صرف للبنود مع قيد كلفة البضاعة، ثم مطابقة الجرد.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT i.id, i.invoice_number FROM invoices i WHERE i.status = 'cancelled'
        AND EXISTS ({$je('Invoice')} AND j.reference_id = i.id AND j.status = 'posted')
        AND NOT EXISTS ({$je('Invoice')} AND j.reference_id = i.id AND j.status = 'reversed')") as $x)
        $items[] = ['table' => 'invoices', 'detail' => "فاتورة ملغاة {$x['invoice_number']} (#{$x['id']}) ما زال قيدها مرحّلاً دون عكس"];
});
$add('cancelled_invoice_posted', 'sales', 'critical', 'فواتير ملغاة ما زال قيدها قائماً',
    'إلغاء الفاتورة يجب أن يعكس قيد الإيراد؛ بقاؤه يضخّم المبيعات والذمم.', 'عكس القيد من شاشة القيود أو إعادة تنفيذ الإلغاء.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT p.id, p.payment_number, p.amount FROM payments p WHERE p.status IN ('completed','refunded') AND ABS(p.amount) > 0 AND NOT EXISTS ({$je('Payment')} AND j.reference_id = p.id)") as $x)
        $items[] = ['table' => 'payments', 'detail' => "دفعة {$x['payment_number']} (#{$x['id']}) بقيمة " . (float) $x['amount'] . ' بدون قيد'];
});
$add('payment_no_journal', 'sales', 'critical', 'مقبوضات بدون قيد محاسبي', 'النقدية في الصندوق لا تطابق الدفاتر.', 'php artisan accounting:backfill لترحيل المدفوعات الناقصة.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT id, quote_number, valid_until, DATEDIFF(NOW(), COALESCE(valid_until, created_at + INTERVAL 30 DAY)) late FROM quotes
        WHERE status IN ('draft','sent') AND COALESCE(valid_until, created_at + INTERVAL 30 DAY) < CURDATE() ORDER BY late DESC") as $x)
        $items[] = ['table' => 'quotes', 'detail' => "عرض {$x['quote_number']} (#{$x['id']}) منتهي الصلاحية منذ {$x['late']} يوماً ولم يُغلق"];
});
$add('quotes_expired_open', 'sales', 'info', 'عروض أسعار منتهية ما زالت مفتوحة', 'تضخّم قائمة المتابعة وتشوّه نسبة التحويل.', 'العروض المُرسلة: تحويلها إلى «منتهي». المسودات لا يسمح النظام بإنهائها مباشرة: أرسلها بعد تمديد الصلاحية أو احذفها.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT qq.id, qq.quote_number FROM quotes qq WHERE qq.status = 'accepted' AND NOT EXISTS (SELECT 1 FROM sales_orders s WHERE s.quote_id = qq.id)") as $x)
        $items[] = ['table' => 'quotes', 'detail' => "عرض مقبول {$x['quote_number']} (#{$x['id']}) لم يتحول إلى أمر بيع"];
});
$add('quotes_accepted_no_so', 'sales', 'warning', 'عروض مقبولة لم تتحول إلى أوامر بيع', 'موافقة العميل دون تنفيذ = مبيعات ضائعة.', 'تحويل العرض إلى أمر بيع من شاشة العروض.', $items);

$aging = [];
$safe(function () use (&$aging, $q) {
    foreach ($q("SELECT CASE WHEN d <= 30 THEN '0-30' WHEN d <= 60 THEN '31-60' WHEN d <= 90 THEN '61-90' ELSE '+90' END b, COUNT(*) n, SUM(due_amount) amt FROM
        (SELECT due_amount, DATEDIFF(CURDATE(), COALESCE(due_date, DATE(created_at))) d FROM invoices WHERE status <> 'cancelled' AND due_amount > 0.01) x GROUP BY b") as $x)
        $aging[$x['b']] = ['n' => (int) $x['n'], 'amount' => round((float) $x['amt'], 2)];
});
$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT i.id, i.invoice_number, i.due_amount, DATEDIFF(CURDATE(), COALESCE(i.due_date, DATE(i.created_at))) d, c.name FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
        WHERE i.status <> 'cancelled' AND i.due_amount > 0.01 AND DATEDIFF(CURDATE(), COALESCE(i.due_date, DATE(i.created_at))) > 30 ORDER BY d DESC") as $x)
        $items[] = ['table' => 'invoices', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}) — {$x['name']} — متبقي " . (float) $x['due_amount'] . " منذ {$x['d']} يوماً"];
});
$add('receivables_overdue', 'sales', 'warning', 'ذمم عملاء متأخرة أكثر من 30 يوماً', 'تأخر التحصيل يضغط السيولة ويرفع مخاطر الديون المعدومة.', 'متابعة التحصيل مع العملاء المذكورين أو جدولة السداد.', $items);

/* ================= PURCHASING checks ================= */
$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT p.id, p.order_number, p.status, DATEDIFF(NOW(), p.created_at) age, sp.name sup, s.order_number so, s.status so_status FROM purchase_orders p
        LEFT JOIN suppliers sp ON sp.id = p.supplier_id LEFT JOIN sales_orders s ON s.id = p.sales_order_id
        WHERE p.status IN ('pending','confirmed') AND p.created_at < NOW() - INTERVAL 14 DAY
        AND NOT EXISTS (SELECT 1 FROM purchase_receipts r WHERE r.purchase_order_id = p.id) ORDER BY p.created_at") as $x)
        $items[] = ['table' => 'purchase_orders', 'detail' => "أمر شراء {$x['order_number']} (#{$x['id']}) — {$x['sup']} — حالته {$x['status']} منذ {$x['age']} يوماً دون استلام" . ($x['so'] ? " — لأمر البيع {$x['so']} ({$x['so_status']})" : '')];
});
$add('po_not_received', 'purchasing', 'warning', 'أوامر شراء لم تُستلم بعد 14 يوماً', 'بضاعة متوقعة لم تصل أو أوامر نُسيت في النظام فتُظهر كميات واردة غير حقيقية.', 'متابعة المورد أو إلغاء الأمر.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT p.id, p.order_number, SUM(i.quantity) ordered, SUM(COALESCE(i.received_quantity,0)) rcv FROM purchase_orders p JOIN purchase_order_items i ON i.purchase_order_id = p.id
        WHERE p.status = 'completed' GROUP BY p.id, p.order_number HAVING rcv + 0.001 < ordered") as $x)
        $items[] = ['table' => 'purchase_orders', 'detail' => "أمر شراء مكتمل {$x['order_number']} (#{$x['id']}): مستلم " . (float) $x['rcv'] . ' من ' . (float) $x['ordered']];
    foreach ($q("SELECT p.id, p.order_number, p.status FROM purchase_orders p JOIN purchase_order_items i ON i.purchase_order_id = p.id
        WHERE p.status IN ('pending','confirmed') GROUP BY p.id, p.order_number, p.status HAVING SUM(COALESCE(i.received_quantity,0)) + 0.001 >= SUM(i.quantity) AND SUM(i.quantity) > 0") as $x)
        $items[] = ['table' => 'purchase_orders', 'detail' => "أمر شراء {$x['order_number']} (#{$x['id']}) استُلم بالكامل وحالته ما زالت {$x['status']}"];
    foreach ($q("SELECT id, order_number, SUM(rcv) rcv, SUM(q) q FROM (SELECT p.id, p.order_number, i.received_quantity rcv, i.quantity q FROM purchase_orders p JOIN purchase_order_items i ON i.purchase_order_id = p.id WHERE i.received_quantity > i.quantity + 0.001) x GROUP BY id, order_number") as $x)
        $items[] = ['table' => 'purchase_order_items', 'detail' => "أمر شراء {$x['order_number']} (#{$x['id']}): استُلم " . (float) $x['rcv'] . ' أكثر من المطلوب ' . (float) $x['q']];
});
$add('po_status_vs_received', 'purchasing', 'warning', 'حالة أمر الشراء لا تطابق الكميات المستلمة', 'حالة خاطئة تُخفي نواقص عند المورد أو تُبقي أوامر منتهية مفتوحة. الاستلام الزائد غالباً اختلاف وحدة (علبة/دزينة مقابل قطعة) بين الطلب والاستلام.', 'تصحيح الحالة، أو تصحيح وحدة/كمية بند الطلب عند اختلاف الوحدة؛ إغلاق أمر بنقص مقبول إذا قرر المورد عدم توريد الباقي.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT r.id, r.receipt_number FROM purchase_receipts r WHERE r.status = 'received' AND NOT EXISTS ({$je('PurchaseReceipt')} AND j.reference_id = r.id)") as $x)
        $items[] = ['table' => 'purchase_receipts', 'detail' => "إيصال {$x['receipt_number']} (#{$x['id']}) بدون قيد استلام (مخزون/ذمم موردين)"];
    foreach ($q("SELECT r.id, r.receipt_number FROM purchase_receipts r WHERE r.status = 'received' AND NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.reference = r.receipt_number OR (m.source = 'purchase_receipt' AND m.movement_key LIKE CONCAT('%receipt:', r.id, ':%')))") as $x)
        $items[] = ['table' => 'purchase_receipts', 'detail' => "إيصال {$x['receipt_number']} (#{$x['id']}) لم يُدخل أي مخزون"];
});
$add('receipt_postings', 'purchasing', 'critical', 'إيصالات استلام ناقصة الترحيل', 'الاستلام يجب أن يُدخل المخزون ويُنشئ قيد ذمم المورد معاً.', 'إعادة ترحيل الإيصال أو تسجيل الحركة/القيد الناقص.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT p.id, p.payment_number, p.amount FROM supplier_payments p WHERE p.status = 'completed' AND p.deleted_at IS NULL AND NOT EXISTS ({$je('SupplierPayment')} AND j.reference_id = p.id)") as $x)
        $items[] = ['table' => 'supplier_payments', 'detail' => "دفعة مورد {$x['payment_number']} (#{$x['id']}) بقيمة " . (float) $x['amount'] . ' بدون قيد'];
    foreach ($q("SELECT r.id, r.return_number FROM purchase_returns r WHERE r.status = 'completed' AND r.deleted_at IS NULL AND NOT EXISTS ({$je('PurchaseReturn')} AND j.reference_id = r.id)") as $x)
        $items[] = ['table' => 'purchase_returns', 'detail' => "مرتجع مشتريات {$x['return_number']} (#{$x['id']}) بدون قيد"];
});
$add('supplier_postings', 'purchasing', 'critical', 'مدفوعات موردين أو مرتجعات بدون قيد', 'ذمم الموردين في الدفاتر لا تطابق ما دُفع/أُرجع فعلاً.', 'php artisan accounting:backfill لترحيل الناقص.', $items);

/* ================= RETURNS checks ================= */
$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT id, rma_number, status, DATEDIFF(NOW(), COALESCE(approved_at, updated_at)) age FROM rma_requests WHERE deleted_at IS NULL AND status IN ('pending','approved','received') AND COALESCE(approved_at, updated_at) < NOW() - INTERVAL 7 DAY") as $x)
        $items[] = ['table' => 'rma_requests', 'detail' => "طلب إرجاع {$x['rma_number']} (#{$x['id']}) حالته {$x['status']} منذ {$x['age']} يوماً"];
});
$add('rma_stuck', 'returns', 'warning', 'طلبات إرجاع عالقة أكثر من 7 أيام', 'العميل ينتظر استرداداً أو بضاعة مُرجعة لم تُعد للمخزون.', 'استلام البضاعة وإكمال التسوية أو رفض الطلب.', $items);

$items = [];
$safe(function () use (&$items, $q, $je) {
    foreach ($q("SELECT r.id, r.rma_number FROM rma_requests r WHERE r.status = 'completed' AND r.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM credit_notes c WHERE c.rma_request_id = r.id AND c.deleted_at IS NULL)") as $x)
        $items[] = ['table' => 'rma_requests', 'detail' => "طلب إرجاع مكتمل {$x['rma_number']} (#{$x['id']}) بدون إشعار دائن"];
    foreach ($q("SELECT c.id, c.credit_note_number FROM credit_notes c WHERE c.deleted_at IS NULL AND c.status = 'applied' AND NOT EXISTS ({$je('CreditNote')} AND j.reference_id = c.id)") as $x)
        $items[] = ['table' => 'credit_notes', 'detail' => "إشعار دائن {$x['credit_note_number']} (#{$x['id']}) بدون قيد"];
    foreach ($q("SELECT r.id, r.rma_number, r.reason FROM rma_requests r WHERE r.status = 'completed' AND r.deleted_at IS NULL
        AND COALESCE(r.reason,'') NOT IN ('defective','damaged','wrong_item_damaged','expired') AND NOT EXISTS (SELECT 1 FROM stock_movements m WHERE m.source = 'rma' AND (m.reference = r.rma_number OR m.movement_key LIKE CONCAT('%rma:', r.id, ':%') OR m.movement_key LIKE CONCAT('%', r.rma_number, '%')))") as $x)
        $items[] = ['table' => 'rma_requests', 'detail' => "طلب إرجاع مكتمل {$x['rma_number']} (#{$x['id']}) سببه «{$x['reason']}» ولم يُعِد أي كمية للمخزون"];
});
$add('rma_postings', 'returns', 'warning', 'مرتجعات مكتملة ناقصة المستندات أو الترحيل', 'الإرجاع المكتمل يُفترض أن يُنتج إشعار دائن وقيداً وحركة إدخال للمخزون (إن كانت البضاعة صالحة).', 'إن عادت البضاعة سليمة فسجّل حركة إدخال للمخزون؛ المرتجعات بسبب عيب/تلف مستثناة من هذا الفحص.', $items);

/* ================= LEDGER checks ================= */
$items = [];
$safe(function () use (&$items, $q) {
    $n = (int) $q("SELECT COUNT(*) n FROM stock_movements WHERE COALESCE(reference,'') = '' AND COALESCE(source,'') = '' AND COALESCE(movement_key,'') = ''")[0]['n'];
    if ($n) $items[] = ['table' => 'stock_movements', 'detail' => "{$n} حركة مخزون بلا مرجع ولا مصدر ولا مفتاح — لا يمكن ربطها بأي مستند", 'sample' => array_column($q("SELECT id v FROM stock_movements WHERE COALESCE(reference,'') = '' AND COALESCE(source,'') = '' AND COALESCE(movement_key,'') = '' ORDER BY id DESC LIMIT 8"), 'v')];
    $n = (int) $q("SELECT COUNT(*) n FROM journal_entry_headers WHERE deleted_at IS NULL AND COALESCE(reference_type,'') = '' AND COALESCE(source_module,'') = ''")[0]['n'];
    if ($n) $items[] = ['table' => 'journal_entry_headers', 'detail' => "{$n} قيد يدوي بلا مستند مرجعي ولا وحدة مصدر"];
});
$add('untraceable', 'ledger', 'info', 'حركات وقيود غير قابلة للتتبع', 'تصعّب التدقيق: لا يمكن معرفة المستند الذي أنتجها.', 'إلزام إدخال سبب/مرجع للحركات اليدوية.', $items);

$items = [];
$safe(function () use (&$items, $q) {
    foreach ($q("SELECT posting_key k, COUNT(*) n FROM journal_entry_headers WHERE deleted_at IS NULL AND status = 'posted' AND posting_key IS NOT NULL AND posting_key <> '' GROUP BY posting_key HAVING n > 1") as $x)
        $items[] = ['table' => 'journal_entry_headers', 'detail' => "مفتاح الترحيل {$x['k']} مرحّل {$x['n']} مرات"];
    foreach ($q("SELECT movement_key k, COUNT(*) n FROM stock_movements WHERE movement_key IS NOT NULL AND movement_key <> '' GROUP BY movement_key HAVING n > 1 LIMIT 50") as $x)
        $items[] = ['table' => 'stock_movements', 'detail' => "مفتاح الحركة {$x['k']} مكرر {$x['n']} مرات"];
});
$add('double_posting', 'ledger', 'critical', 'ترحيل مكرر لنفس المستند', 'مفاتيح الترحيل وُضعت لمنع التكرار؛ تكرارها يعني مضاعفة إيراد/كلفة أو كمية.', 'عكس القيد/الحركة المكررة.', $items);

/* ================= modules & app integrity ================= */
$modules = [];
foreach (['orders' => 'طلبات المتجر الإلكتروني', 'production_orders' => 'أوامر الإنتاج', 'product_assembly_orders' => 'أوامر التجميع', 'packing_lists' => 'قوائم التعبئة',
    'shipping_manifests' => 'بيانات الشحن', 'expenses' => 'المصاريف', 'payrolls' => 'الرواتب', 'leave_requests' => 'الإجازات', 'workflows' => 'محرك مسارات العمل',
    'sales_contracts' => 'عقود المبيعات', 'tickets' => 'تذاكر الدعم', 'inquiries' => 'استفسارات الموقع', 'cycle_counts' => 'الجرد الدوري', 'budgets' => 'الموازنات', 'fixed_assets' => 'الأصول الثابتة'] as $t => $l) {
    if ($has($t)) $modules[] = ['table' => $t, 'label' => $l, 'rows' => (int) DB::table($t)->count()];
}

$integrity = null;
$log = storage_path('logs/integrity.log');
if (is_file($log)) {
    $lines = array_slice(file($log, FILE_IGNORE_NEW_LINES), -40);
    $start = null;
    foreach ($lines as $i => $l) if (str_starts_with($l, '== ')) $start = $i;
    if ($start !== null) $integrity = ['at' => trim(substr($lines[$start], 3)), 'lines' => array_values(array_filter(array_slice($lines, $start + 1, 10), fn ($l) => trim($l) !== '' && !str_starts_with($l, '== ')))];
}

$w = ['critical' => 12, 'warning' => 5, 'info' => 1, 'ok' => 0, 'error' => 0];
$score = max(0, 100 - array_sum(array_map(fn ($c) => $w[$c['sev']] * min(3, max(1, (int) ceil($c['count'] / 5))), $checks)));

file_put_contents(getenv('WF_OUT'), json_encode([
    'generated_at' => now()->format('Y-m-d H:i'),
    'score' => $score,
    'flows' => $flows,
    'aging' => $aging,
    'checks' => $checks,
    'modules' => $modules,
    'integrity' => $integrity,
], JSON_UNESCAPED_UNICODE));
echo 'ok';
