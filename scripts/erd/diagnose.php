<?php
/*
 * Read-only DB health diagnostics. Run through tinker (see build.sh);
 * writes JSON to $DIAG_OUT. Every query here is a SELECT / information_schema read.
 */

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$db = DB::getDatabaseName();
// information_schema caches table stats for 24h by default; without this, sizes/free space are stale.
try { DB::statement('SET SESSION information_schema_stats_expiry = 0'); } catch (\Throwable $e) {}
$q = fn (string $sql, array $b = []) => array_map(fn ($r) => (array) $r, DB::select($sql, $b));
$one = fn (string $sql, array $b = []) => (array) DB::selectOne($sql, $b);
$id = fn (string $s) => '`' . str_replace('`', '``', $s) . '`';

$tables = array_column($q("SELECT TABLE_NAME n FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'", [$db]), 'n');
$tset = array_flip($tables);
$cols = [];
foreach ($q("SELECT TABLE_NAME t, COLUMN_NAME n, COLUMN_TYPE ty, DATA_TYPE dt, IS_NULLABLE nu, COLUMN_KEY k FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION", [$db]) as $c) {
    $cols[$c['t']][$c['n']] = $c;
}
$fks = $q("SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL", [$db]);
$fkCols = [];
foreach ($fks as $f) $fkCols[$f['t']][$f['c']] = $f;
$idxRows = $q("SELECT TABLE_NAME t, INDEX_NAME i, NON_UNIQUE nu, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE", [$db]);
$idx = [];
foreach ($idxRows as $r) $idx[$r['t']][$r['i']] = ['cols' => explode(',', $r['cols']), 'unique' => (int) $r['nu'] === 0];
$leadIndexed = fn ($t, $c) => collect($idx[$t] ?? [])->contains(fn ($i) => $i['cols'][0] === $c);
$uniqueOn = fn ($t, $c) => collect($idx[$t] ?? [])->contains(fn ($i) => $i['unique'] && $i['cols'] === [$c]);

$checks = [];
$add = function (string $id, string $cat, string $sev, string $title, string $why, string $fix, array $items) use (&$checks, &$errors) {
    $err = $errors; $errors = [];
    $checks[] = ['id' => $id, 'cat' => $cat, 'sev' => $err ? 'error' : ($items ? $sev : 'ok'), 'error' => $err ? mb_substr(implode(' | ', array_unique($err)), 0, 300) : null, 'title' => $title, 'why' => $why, 'fix' => $fix, 'count' => count($items), 'items' => array_slice($items, 0, 200)];
};
$errors = [];
$safe = function (callable $fn, $fallback = null) use (&$errors) { try { return $fn(); } catch (\Throwable $e) { $errors[] = $e->getMessage(); return $fallback; } };

/* ---------- exact counts & sizes ---------- */
$rows = [];
foreach ($tables as $t) $rows[$t] = (int) $one("SELECT COUNT(*) n FROM {$id($t)}")['n'];
$sizes = [];
foreach ($q("SELECT TABLE_NAME n, DATA_LENGTH dl, INDEX_LENGTH il, DATA_FREE df, AUTO_INCREMENT ai, ENGINE e, TABLE_COLLATION co FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'", [$db]) as $s) {
    $sizes[$s['n']] = ['data' => (int) $s['dl'], 'index' => (int) $s['il'], 'free' => (int) $s['df'], 'ai' => $s['ai'] === null ? null : (int) $s['ai'], 'engine' => $s['e'], 'collation' => $s['co']];
}

/* ---------- logical (unconstrained) references ---------- */
$resolve = function (string $col) use ($tset) {
    $g = preg_replace('/_id$/', '', $col);
    $alias = ['created_by' => 'users', 'parent' => null, 'assigned_employee' => 'employees', 'fulfillment_warehouse' => 'warehouses', 'account' => 'ledger_accounts', 'channel' => 'order_channels', 'contract' => 'sales_contracts', 'journal_entry_header' => 'journal_entry_headers', 'reversal_of' => null];
    if (array_key_exists($g, $alias)) return $alias[$g];
    foreach ([$g . 's', $g . 'es', preg_replace('/y$/', 'ies', $g), $g] as $cand) if (isset($tset[$cand])) return $cand;
    return null;
};
$logical = [];
foreach ($cols as $t => $cs) {
    foreach ($cs as $c => $meta) {
        if (!preg_match('/_id$/', $c) || isset($fkCols[$t][$c])) continue;
        $base = preg_replace('/_id$/', '', $c);
        if (isset($cs[$base . '_type'])) continue; // polymorphic
        if (in_array($t, ['sessions', 'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks', 'password_reset_tokens'], true)) continue;
        if ($c === 'reference_id' || $c === 'model_id' || $c === 'session_id' || $c === 'external_order_id') continue;
        $rt = $resolve($c);
        if ($rt && isset($cols[$rt]['id'])) $logical[] = ['t' => $t, 'c' => $c, 'rt' => $rt, 'rc' => 'id'];
    }
}
// self-references named parent_id / reversal_of_id
foreach ($cols as $t => $cs) foreach (['parent_id', 'reversal_of_id'] as $c) if (isset($cs[$c]) && !isset($fkCols[$t][$c])) $logical[] = ['t' => $t, 'c' => $c, 'rt' => $t, 'rc' => 'id'];

/* ===================== SCHEMA ===================== */
$add('no_pk', 'schema', 'critical', 'جداول بدون مفتاح أساسي (PK)',
    'غياب المفتاح الأساسي يمنع تحديد السجل بدقة ويبطئ التكرار (Replication) والتعديل.',
    'إضافة عمود id أو مفتاح أساسي مركّب مناسب.',
    array_values(array_map(fn ($t) => ['table' => $t, 'detail' => 'لا يوجد PRIMARY KEY'], array_filter($tables, fn ($t) => !isset($idx[$t]['PRIMARY'])))));

$mis = [];
foreach ($fks as $f) {
    $a = $cols[$f['t']][$f['c']]['ty'] ?? null; $b = $cols[$f['rt']][$f['rc']]['ty'] ?? null;
    if ($a && $b && $a !== $b) $mis[] = ['table' => $f['t'], 'column' => $f['c'], 'detail' => "{$a} ≠ {$f['rt']}.{$f['rc']} ({$b})"];
}
foreach ($logical as $f) {
    $a = $cols[$f['t']][$f['c']]['ty']; $b = $cols[$f['rt']][$f['rc']]['ty'];
    $norm = fn ($x) => preg_replace('/\(\d+\)/', '', $x);
    if ($norm($a) !== $norm($b)) $mis[] = ['table' => $f['t'], 'column' => $f['c'], 'detail' => "{$a} ≠ {$f['rt']}.{$f['rc']} ({$b}) — مرجع منطقي"];
}
$add('type_mismatch', 'schema', 'warning', 'عدم تطابق نوع عمود المرجع مع المفتاح المُشار إليه',
    'اختلاف النوع (مثل int مقابل bigint unsigned) يمنع إنشاء قيد FK ويُضعف استخدام الفهارس في الربط.',
    'توحيد نوع العمود مع نوع المفتاح الأساسي المُشار إليه.', $mis);

$add('logical_refs', 'schema', 'warning', 'أعمدة مرجعية بدون قيد مفتاح أجنبي (FK)',
    'قاعدة البيانات لا تمنع إدخال قيم غير موجودة في الجدول الأب ولا تمنع حذف الأب، فتنشأ سجلات يتيمة.',
    'إضافة قيد foreignId()->constrained() بعد تنظيف السجلات اليتيمة.',
    array_map(fn ($f) => ['table' => $f['t'], 'column' => $f['c'], 'detail' => "يُفترض أنه يشير إلى {$f['rt']}.{$f['rc']}"], $logical));

$noIdx = [];
foreach ($logical as $f) if (!$leadIndexed($f['t'], $f['c'])) $noIdx[] = ['table' => $f['t'], 'column' => $f['c'], 'detail' => 'لا يوجد فهرس يبدأ بهذا العمود', 'rows' => $rows[$f['t']]];
$add('unindexed_refs', 'schema', 'warning', 'أعمدة ربط بدون فهرس',
    'كل JOIN أو WHERE على هذه الأعمدة يقرأ الجدول كاملاً؛ يزداد البطء مع نمو البيانات.',
    'إضافة فهرس: $table->index(\'column\').', $noIdx);

$red = [];
foreach ($idx as $t => $list) foreach ($list as $n => $i) {
    if ($n === 'PRIMARY' || $i['unique']) continue;
    foreach ($list as $n2 => $j) {
        if ($n2 === $n) continue;
        $prefix = array_slice($j['cols'], 0, count($i['cols'])) === $i['cols'];
        if ($prefix && (count($j['cols']) > count($i['cols']) || $n2 < $n)) { $red[] = ['table' => $t, 'detail' => "الفهرس {$n} (" . implode(',', $i['cols']) . ") مغطى بالفهرس {$n2} (" . implode(',', $j['cols']) . ')']; break; }
    }
}
$add('redundant_idx', 'schema', 'info', 'فهارس مكررة أو زائدة',
    'الفهرس الذي تغطيه بداية فهرس آخر لا يفيد القراءة، ويبطئ الكتابة ويستهلك مساحة. (قد يحتاجه قيد FK — تحقق قبل الحذف.)',
    'حذف الفهرس الزائد إن لم يكن مطلوباً لقيد FK.', $red);

$big = [];
foreach ($cols as $t => $cs) foreach ($cs as $c => $m) {
    if ($m['k'] !== 'PRI' || !preg_match('/int/', $m['dt']) || ($sizes[$t]['ai'] ?? null) === null) continue;
    $uns = str_contains($m['ty'], 'unsigned');
    $max = ['tinyint' => 127, 'smallint' => 32767, 'mediumint' => 8388607, 'int' => 2147483647, 'bigint' => 9223372036854775807][$m['dt']] ?? null;
    if (!$max) continue; if ($uns) $max = $max * 2 + 1;
    $pct = $sizes[$t]['ai'] / $max * 100;
    if ($pct >= 50) $big[] = ['table' => $t, 'column' => $c, 'detail' => sprintf('AUTO_INCREMENT عند %s%% من الحد الأقصى', round($pct, 1))];
}
$add('ai_capacity', 'schema', 'critical', 'اقتراب العدّاد التلقائي من الحد الأقصى',
    'عند بلوغ الحد يتوقف الإدخال في الجدول.', 'تحويل العمود إلى bigint unsigned.', $big);

$coll = collect($sizes)->groupBy(fn ($s) => $s['engine'] . ' / ' . $s['collation']);
$mixed = $coll->count() > 1 ? $coll->map(fn ($g, $k) => ['detail' => "{$k}: " . $g->count() . ' جدول'])->values()->all() : [];
$add('engine_collation', 'schema', 'warning', 'تجانس محرك التخزين وترميز الأحرف',
    'خلط المحركات أو الترميزات يسبب أخطاء "Illegal mix of collations" ويمنع قيود FK.',
    'توحيد الجداول على InnoDB و utf8mb4_unicode_ci.', $mixed);

$pending = [];
$safe(function () use (&$pending) {
    $ran = DB::table('migrations')->pluck('migration')->flip();
    foreach (glob(database_path('migrations/*.php')) as $f) { $n = basename($f, '.php'); if (!isset($ran[$n])) $pending[] = ['detail' => $n]; }
});
$add('pending_migrations', 'schema', 'critical', 'ترحيلات (Migrations) غير منفّذة',
    'الكود يتوقع أعمدة/جداول غير موجودة بعد في قاعدة البيانات — قد تفشل بعض الصفحات أو العمليات.',
    'تشغيل: php artisan migrate --force (بعد أخذ نسخة احتياطية).', $pending);

/* ===================== INTEGRITY ===================== */
$orph = function (array $f) use ($id, $one) {
    return (int) $one("SELECT COUNT(*) n FROM {$id($f['t'])} c LEFT JOIN {$id($f['rt'])} p ON p.{$id($f['rc'])} = c.{$id($f['c'])} WHERE c.{$id($f['c'])} IS NOT NULL AND p.{$id($f['rc'])} IS NULL")['n'];
};
$sample = function (array $f) use ($id, $q) {
    return array_column($q("SELECT DISTINCT c.{$id($f['c'])} v FROM {$id($f['t'])} c LEFT JOIN {$id($f['rt'])} p ON p.{$id($f['rc'])} = c.{$id($f['c'])} WHERE c.{$id($f['c'])} IS NOT NULL AND p.{$id($f['rc'])} IS NULL LIMIT 8"), 'v');
};
$o1 = [];
foreach ($fks as $f) if ($n = $safe(fn () => $orph($f), 0)) $o1[] = ['table' => $f['t'], 'column' => $f['c'], 'detail' => "{$n} سجل يشير إلى {$f['rt']} غير موجود", 'n' => $n, 'sample' => $sample($f)];
$add('orphans_fk', 'integrity', 'critical', 'سجلات يتيمة رغم وجود قيد FK',
    'يعني أن البيانات أُدخلت أو استوردت مع تعطيل FOREIGN_KEY_CHECKS.',
    'حذف السجلات اليتيمة أو تصحيح المرجع، ثم عدم تعطيل الفحص عند الاستيراد.', $o1);

$o2 = [];
foreach ($logical as $f) if ($n = $safe(fn () => $orph($f), 0)) $o2[] = ['table' => $f['t'], 'column' => $f['c'], 'detail' => "{$n} سجل يشير إلى {$f['rt']}.{$f['rc']} غير موجود", 'n' => $n, 'sample' => $sample($f)];
$add('orphans_logical', 'integrity', 'warning', 'سجلات يتيمة في المراجع المنطقية',
    'سجلات تشير إلى أب محذوف أو غير موجود؛ تظهر كقيم فارغة أو تسبب أخطاء في التقارير والشاشات.',
    'تصحيح المرجع أو جعله NULL، ثم إضافة قيد FK لمنع التكرار.', $o2);

$dups = [];
foreach ($cols as $t => $cs) foreach ($cs as $c => $m) {
    if (!preg_match('/^(sku|barcode|slug|email|code|[a-z_]*_number)$/', $c) || $uniqueOn($t, $c) || $rows[$t] === 0) continue;
    if (in_array($c, ['batch_number', 'tracking_number', 'serial_number', 'phone_number', 'account_number', 'tax_number', 'reference_number'], true)) continue;
    $r = $q("SELECT {$id($c)} v, COUNT(*) n FROM {$id($t)} WHERE {$id($c)} IS NOT NULL AND {$id($c)} <> '' GROUP BY {$id($c)} HAVING n > 1 ORDER BY n DESC LIMIT 8");
    if ($r) {
        $tot = (int) $one("SELECT COUNT(*) n FROM (SELECT 1 FROM {$id($t)} WHERE {$id($c)} IS NOT NULL AND {$id($c)} <> '' GROUP BY {$id($c)} HAVING COUNT(*) > 1) x")['n'];
        $dups[] = ['table' => $t, 'column' => $c, 'detail' => "{$tot} قيمة مكررة", 'n' => $tot, 'sample' => array_map(fn ($x) => "{$x['v']} ×{$x['n']}", $r)];
    }
}
$add('duplicates', 'integrity', 'warning', 'قيم مكررة في أعمدة يُفترض أنها فريدة',
    'رموز مثل SKU والباركود وأرقام الفواتير يجب ألا تتكرر؛ التكرار يسبب اختيار منتج/مستند خاطئ عند البحث أو المسح.',
    'تنظيف التكرارات ثم إضافة فهرس UNIQUE.', $dups);

/* ===================== DATA (business rules) ===================== */
$neg = [];
foreach (['products' => ['stock_quantity'], 'product_variants' => ['stock_quantity'], 'warehouse_inventory' => ['quantity', 'available_quantity', 'reserved_quantity']] as $t => $cl) {
    foreach ($cl as $c) if (isset($cols[$t][$c]) && ($n = (int) $one("SELECT COUNT(*) n FROM {$id($t)} WHERE {$id($c)} < 0")['n'])) {
        $neg[] = ['table' => $t, 'column' => $c, 'detail' => "{$n} سجل بكمية سالبة", 'n' => $n, 'sample' => array_column($q("SELECT id v FROM {$id($t)} WHERE {$id($c)} < 0 ORDER BY {$id($c)} LIMIT 8"), 'v')];
    }
}
$add('negative_stock', 'data', 'critical', 'أرصدة مخزون سالبة',
    'رصيد سالب يعني صرف كمية غير موجودة؛ يشوّه كلفة المخزون وقرارات إعادة الطلب.',
    'مراجعة حركات المنتج وإجراء تسوية جرد.', $neg);

$wi = [];
if (isset($cols['warehouse_inventory']['reserved_quantity'])) {
    $n = (int) $one('SELECT COUNT(*) n FROM warehouse_inventory WHERE reserved_quantity > quantity')['n'];
    if ($n) $wi[] = ['table' => 'warehouse_inventory', 'column' => 'reserved_quantity', 'detail' => "{$n} سجل: المحجوز أكبر من الرصيد", 'n' => $n, 'sample' => array_column($q('SELECT id v FROM warehouse_inventory WHERE reserved_quantity > quantity LIMIT 8'), 'v')];
    $n = (int) $one('SELECT COUNT(*) n FROM warehouse_inventory WHERE available_quantity > quantity')['n'];
    if ($n) $wi[] = ['table' => 'warehouse_inventory', 'column' => 'available_quantity', 'detail' => "{$n} سجل: المتاح أكبر من الرصيد", 'n' => $n, 'sample' => array_column($q('SELECT id v FROM warehouse_inventory WHERE available_quantity > quantity LIMIT 8'), 'v')];
}
$add('inventory_buckets', 'data', 'warning', 'تناقض في أرصدة المستودع (متاح/محجوز)',
    'المتاح والمحجوز جزء من الرصيد الكلي ولا يجوز أن يتجاوزاه.', 'إعادة احتساب available = quantity - reserved - damaged - quarantined.', $wi);

$recon = [];
$safe(function () use (&$recon, $q) {
    $r = $q("SELECT m.product_id, m.warehouse_id, m.net, COALESCE(w.qty,0) qty FROM
        (SELECT product_id, warehouse_id, SUM(CASE movement_type WHEN 'out' THEN -quantity ELSE quantity END) net FROM stock_movements WHERE warehouse_id IS NOT NULL GROUP BY product_id, warehouse_id) m
        LEFT JOIN (SELECT product_id, warehouse_id, SUM(quantity) qty FROM warehouse_inventory GROUP BY product_id, warehouse_id) w
        ON w.product_id = m.product_id AND w.warehouse_id = m.warehouse_id
        WHERE ABS(m.net - COALESCE(w.qty,0)) > 0.001 ORDER BY ABS(m.net - COALESCE(w.qty,0)) DESC");
    foreach ($r as $x) $recon[] = ['table' => 'warehouse_inventory', 'column' => 'quantity', 'detail' => sprintf('منتج #%s / مستودع #%s: الحركات = %s، الرصيد = %s', $x['product_id'], $x['warehouse_id'], +$x['net'], +$x['qty'])];
});
$add('stock_reconciliation', 'data', 'warning', 'رصيد المستودع لا يطابق مجموع حركات المخزون',
    'الرصيد في warehouse_inventory يجب أن يساوي (وارد − صادر ± تسويات) من stock_movements. قد يكون الفرق أرصدة افتتاحية أُدخلت دون حركة.',
    'إنشاء حركة adjustment بالفرق أو تصحيح الرصيد بعد التحقق.', $recon);

$pv = [];
$safe(function () use (&$pv, $q) {
    foreach ($q('SELECT p.id, p.stock_quantity s, SUM(w.quantity) w FROM products p JOIN warehouse_inventory w ON w.product_id = p.id GROUP BY p.id, p.stock_quantity HAVING ABS(p.stock_quantity - SUM(w.quantity)) > 0.001') as $x)
        $pv[] = ['table' => 'products', 'column' => 'stock_quantity', 'detail' => sprintf('منتج #%s: products.stock_quantity = %s، مجموع المستودعات = %s', $x['id'], +$x['s'], +$x['w'])];
});
$add('product_stock_cache', 'data', 'info', 'الرصيد المخزّن في المنتج لا يطابق مجموع المستودعات',
    'products.stock_quantity قيمة مجمّعة؛ عدم تحديثها يُظهر توفراً خاطئاً في المتجر والقوائم.',
    'إعادة مزامنة الرصيد من warehouse_inventory.', $pv);

$je = [];
$safe(function () use (&$je, $q) {
    foreach ($q('SELECT h.id, h.entry_number, ROUND(SUM(l.debit),2) d, ROUND(SUM(l.credit),2) c FROM journal_entry_headers h JOIN journal_entry_lines l ON l.journal_entry_header_id = h.id WHERE h.deleted_at IS NULL GROUP BY h.id, h.entry_number HAVING d <> c') as $x)
        $je[] = ['table' => 'journal_entry_lines', 'detail' => "قيد {$x['entry_number']} (#{$x['id']}): مدين {$x['d']} ≠ دائن {$x['c']}"];
    foreach ($q('SELECT h.id, h.entry_number, h.total_debit td, ROUND(SUM(l.debit),2) d FROM journal_entry_headers h JOIN journal_entry_lines l ON l.journal_entry_header_id = h.id WHERE h.deleted_at IS NULL GROUP BY h.id, h.entry_number, h.total_debit HAVING ABS(td - d) > 0.01') as $x)
        $je[] = ['table' => 'journal_entry_headers', 'detail' => "قيد {$x['entry_number']} (#{$x['id']}): إجمالي الرأس {$x['td']} ≠ مجموع الأسطر {$x['d']}"];
    foreach ($q('SELECT h.id, h.entry_number FROM journal_entry_headers h LEFT JOIN journal_entry_lines l ON l.journal_entry_header_id = h.id WHERE h.deleted_at IS NULL AND l.id IS NULL') as $x)
        $je[] = ['table' => 'journal_entry_headers', 'detail' => "قيد {$x['entry_number']} (#{$x['id']}) بدون أسطر"];
});
$add('journal_balance', 'data', 'critical', 'قيود يومية غير متوازنة',
    'كل قيد يجب أن يتساوى فيه المدين والدائن؛ عدم التوازن يُفسد ميزان المراجعة والقوائم المالية.',
    'مراجعة القيد وتصحيح أسطره أو عكسه.', $je);

$inv = [];
$safe(function () use (&$inv, $q) {
    foreach ($q('SELECT i.id, i.invoice_number, i.subtotal s, ROUND(SUM(it.total_price),2) t FROM invoices i JOIN invoice_items it ON it.invoice_id = i.id GROUP BY i.id, i.invoice_number, i.subtotal HAVING ABS(s - t) > 0.01') as $x)
        $inv[] = ['table' => 'invoices', 'column' => 'subtotal', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}): المجموع {$x['s']} ≠ مجموع البنود {$x['t']}"];
    // Returns (RmaSettlementService) lower due_amount by credit applied to the invoice and
    // paid_amount by cash refunded, so: total − paid − due = Σ(applied_to_invoice + refunded_amount).
    // Overpayment (gap < 0) is reported by its own check below.
    foreach ($q('SELECT i.id, i.invoice_number, i.total, i.paid_amount, i.due_amount, COALESCE(SUM(c.applied_to_invoice + c.refunded_amount), 0) cn
        FROM invoices i LEFT JOIN credit_notes c ON c.invoice_id = i.id AND c.deleted_at IS NULL
        WHERE i.status <> \'cancelled\' GROUP BY i.id, i.invoice_number, i.total, i.paid_amount, i.due_amount
        HAVING ABS((i.total - i.paid_amount - i.due_amount) - cn) > 0.01 AND i.paid_amount <= i.total - cn + 0.01') as $x)
        $inv[] = ['table' => 'invoices', 'column' => 'due_amount', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}): المتبقي " . (float) $x['due_amount'] . " ≠ الإجمالي " . (float) $x['total'] . " − المدفوع " . (float) $x['paid_amount'] . " − المرتجعات " . (float) $x['cn']];
    foreach ($q('SELECT i.id, i.invoice_number FROM invoices i LEFT JOIN invoice_items it ON it.invoice_id = i.id WHERE it.id IS NULL') as $x)
        $inv[] = ['table' => 'invoices', 'detail' => "فاتورة {$x['invoice_number']} (#{$x['id']}) بدون بنود"];
});
$add('invoice_totals', 'data', 'warning', 'تناقض في مبالغ الفواتير',
    'المجموع يجب أن يساوي مجموع البنود، والمتبقي = الإجمالي − المدفوع − ما سُوّي بإشعارات دائن (الفواتير الملغاة مستثناة).', 'إعادة احتساب إجماليات الفواتير المذكورة.', $inv);

$over = [];
$safe(function () use (&$over, $q) {
    foreach ($q('SELECT i.id, i.invoice_number, i.total, i.paid_amount, i.customer_id, cu.balance FROM invoices i LEFT JOIN customers cu ON cu.id = i.customer_id
        WHERE i.status <> \'cancelled\' AND i.paid_amount > i.total + 0.01') as $x)
        $over[] = ['table' => 'invoices', 'column' => 'paid_amount', 'detail' => sprintf('فاتورة %s (#%s): مدفوع %s مقابل إجمالي %s — زيادة %s، رصيد العميل #%s الحالي %s',
            $x['invoice_number'], $x['id'], (float) $x['paid_amount'], (float) $x['total'], round($x['paid_amount'] - $x['total'], 2), $x['customer_id'], (float) $x['balance'])];
});
$add('overpayments', 'data', 'info', 'دفعات تزيد على قيمة الفاتورة',
    'الزيادة مسجلة محاسبياً كرصيد دائن للعميل (القيد متوازن)، لكنها قد تكون باقي نقدية لم يُسجَّل إرجاعه بدلاً من رصيد مقصود.',
    'التأكد من الكاشير: إن أُرجع الباقي نقداً فسجّل استرداداً بقيمة الزيادة؛ وإلا فهو رصيد للعميل ولا يلزم إجراء.', $over);

$ts = [];
foreach ($cols as $t => $cs) if (isset($cs['created_at'], $cs['updated_at']) && $rows[$t]) {
    $a = (int) $one("SELECT COUNT(*) n FROM {$id($t)} WHERE updated_at < created_at")['n'];
    $b = (int) $one("SELECT COUNT(*) n FROM {$id($t)} WHERE created_at > NOW() + INTERVAL 1 DAY")['n'];
    if ($a) $ts[] = ['table' => $t, 'column' => 'updated_at', 'detail' => "{$a} سجل: تاريخ التعديل قبل تاريخ الإنشاء", 'n' => $a];
    if ($b) $ts[] = ['table' => $t, 'column' => 'created_at', 'detail' => "{$b} سجل بتاريخ إنشاء في المستقبل", 'n' => $b];
}
$add('timestamps', 'data', 'info', 'تواريخ غير منطقية', 'تدل على استيراد يدوي أو اختلاف توقيت الخادم.', 'مراجعة مصدر البيانات وتوحيد المنطقة الزمنية.', $ts);

/* ===================== OPERATIONS ===================== */
$frag = [];
foreach ($sizes as $t => $s) { $tot = $s['data'] + $s['index']; if ($s['free'] > 10 * 1048576 && $s['free'] > 0.2 * $tot) $frag[] = ['table' => $t, 'detail' => sprintf('%.1f MB مساحة غير مستغلة', $s['free'] / 1048576)]; }
$add('fragmentation', 'ops', 'info', 'تجزؤ ومساحات غير مستغلة', 'مساحة محجوزة بعد عمليات حذف كثيرة.', 'OPTIMIZE TABLE في وقت قليل الاستخدام.', $frag);

$growth = [];
foreach (['visitors', 'site_visitors', 'sessions', 'audit_logs', 'notifications', 'analytics_data_points'] as $t) {
    if (!isset($sizes[$t])) continue; $mb = ($sizes[$t]['data'] + $sizes[$t]['index']) / 1048576;
    if ($mb >= 5 || $rows[$t] >= 50000) $growth[] = ['table' => $t, 'detail' => sprintf('%s سجل — %.1f MB', number_format($rows[$t]), $mb)];
}
$add('log_growth', 'ops', 'info', 'جداول سجلات تنمو دون حد', 'جداول الزيارات والجلسات والسجلات تكبر باستمرار وتبطئ النسخ الاحتياطي.', 'جدولة حذف دوري (مثلاً model:prune أو أمر يحذف ما هو أقدم من 90 يوماً).', $growth);

$jobs = [];
foreach (['failed_jobs' => 'مهمة فاشلة', 'jobs' => 'مهمة في الطابور'] as $t => $l) if (($rows[$t] ?? 0) > 0) $jobs[] = ['table' => $t, 'detail' => "{$rows[$t]} {$l}"];
$add('queue', 'ops', 'warning', 'مهام خلفية فاشلة أو متراكمة', 'تعني أن إشعارات أو عمليات لم تُنفّذ.', 'php artisan queue:failed ثم queue:retry، والتأكد من تشغيل queue:work.', $jobs);

/* ---------- summary ---------- */
$version = $one('SELECT VERSION() v')['v'];
$totalMb = array_sum(array_map(fn ($s) => $s['data'] + $s['index'], $sizes)) / 1048576;
$sevW = ['critical' => 15, 'warning' => 5, 'info' => 1, 'ok' => 0, 'error' => 0];
$score = max(0, 100 - array_sum(array_map(fn ($c) => $sevW[$c['sev']] * min(3, max(1, (int) ceil($c['count'] / 10))) * ($c['sev'] === 'ok' ? 0 : 1), $checks)));

file_put_contents(getenv('DIAG_OUT'), json_encode([
    'generated_at' => now()->format('Y-m-d H:i'),
    'server' => ['version' => $version, 'size_mb' => round($totalMb, 1), 'tables' => count($tables), 'rows' => array_sum($rows), 'empty' => count(array_filter($rows, fn ($n) => $n === 0))],
    'score' => $score,
    'checks' => $checks,
    'rows' => $rows,
    'sizes' => array_map(fn ($s) => ['mb' => round(($s['data'] + $s['index']) / 1048576, 2), 'free_mb' => round($s['free'] / 1048576, 2)], $sizes),
], JSON_UNESCAPED_UNICODE));
echo 'ok';
