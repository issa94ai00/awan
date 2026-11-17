#!/usr/bin/env python3
"""Build a self-contained erd.html (DB docs + interactive ERD) from schema.json."""
import json, sys, datetime

src, out = sys.argv[1], sys.argv[2]
d = json.load(open(src))

GROUPS = [
    ("accounting", "المحاسبة والمالية", ["accounting_periods", "bank_reconciliation_lines", "bank_reconciliations", "budget_lines", "budgets", "cost_centers", "currencies", "currency_rates", "expenses", "fixed_assets", "journal_entry_headers", "journal_entry_lines", "ledger_accounts", "payment_methods", "payments", "supplier_payments", "wallet_transactions"]),
    ("sales", "المبيعات والطلبات", ["sales_orders", "sales_order_items", "sales_order_item_allocations", "sales_order_revisions", "sales_order_routings", "sales_order_status_histories", "sales_contracts", "quotes", "quote_items", "invoices", "invoice_items", "credit_notes", "credit_note_items", "price_offer_lists", "price_offer_list_items", "orders", "order_items", "order_channels", "special_offers", "carts", "cart_items", "wishlist_items"]),
    ("purchases", "المشتريات والموردون", ["suppliers", "purchase_orders", "purchase_order_items", "purchase_receipts", "purchase_receipt_items", "purchase_returns", "purchase_return_items", "landed_costs"]),
    ("inventory", "المخزون والمستودعات", ["warehouses", "warehouse_bins", "warehouse_inventory", "bin_assignments", "stock_movements", "inventory_cost_layers", "inventory_transfers", "inventory_transfer_items", "cycle_counts", "cycle_count_items", "picking_lists", "picking_list_items", "packing_lists", "packing_list_items", "shipping_manifests", "shipping_manifest_items", "reorder_alerts", "product_warehouse_assignments"]),
    ("products", "المنتجات والتصنيع", ["products", "categories", "product_units", "product_variants", "product_batches", "product_serial_numbers", "product_components", "product_assembly_orders", "product_assembly_order_items", "production_orders", "reviews"]),
    ("crm", "العملاء وخدمة ما بعد البيع", ["customers", "customer_employee", "contacts", "addresses", "companies", "inquiries", "inquiry_replies", "tickets", "rma_requests", "rma_items", "subscribers"]),
    ("hr", "الموارد البشرية", ["employees", "employee_commissions", "employee_commission_withdrawals", "attendances", "leave_requests", "payrolls"]),
    ("access", "المستخدمون والصلاحيات", ["users", "roles", "permissions", "model_has_roles", "model_has_permissions", "role_has_permissions", "features", "feature_role", "personal_access_tokens", "password_reset_tokens", "sessions"]),
    ("analytics", "التقارير والتحليلات والتدقيق", ["reports", "dashboards", "dashboard_widgets", "analytics_metrics", "analytics_data_points", "audit_logs", "site_visitors", "visitors"]),
    ("system", "النظام والإعدادات", ["settings", "integration_settings", "notifications", "notification_templates", "notification_preferences", "workflows", "workflow_steps", "workflow_executions", "cache", "cache_locks", "jobs", "job_batches", "failed_jobs", "migrations"]),
]

DESC = {
    "accounting_periods": "الفترات المحاسبية (سنوات/أشهر مالية) وحالة إقفالها.",
    "addresses": "عناوين مرتبطة بالعملاء أو الموردين أو غيرهم (علاقة متعددة الأشكال).",
    "analytics_data_points": "قيم زمنية مسجلة لكل مؤشر تحليلي.",
    "analytics_metrics": "تعريف المؤشرات التحليلية (KPIs) وطريقة حسابها.",
    "attendances": "سجل حضور وانصراف الموظفين.",
    "audit_logs": "سجل التدقيق: من غيّر ماذا ومتى، مع القيم القديمة والجديدة.",
    "bank_reconciliation_lines": "بنود مطابقة كشف البنك مع القيود.",
    "bank_reconciliations": "عمليات مطابقة الحسابات البنكية.",
    "bin_assignments": "تخصيص المنتجات لمواقع التخزين (الرفوف/الخانات).",
    "budget_lines": "بنود الموازنة حسب الحساب ومركز الكلفة.",
    "budgets": "الموازنات التقديرية لكل فترة.",
    "cache": "تخزين مؤقت لإطار Laravel.",
    "cache_locks": "أقفال التخزين المؤقت لإطار Laravel.",
    "cart_items": "عناصر سلة التسوق.",
    "carts": "سلال التسوق للزوار/العملاء في المتجر.",
    "categories": "تصنيفات المنتجات (شجرية: قسم أب وأقسام فرعية).",
    "companies": "بيانات الشركات/الجهات التجارية.",
    "contacts": "جهات الاتصال التابعة للعملاء أو الشركات.",
    "cost_centers": "مراكز الكلفة لتوزيع المصاريف والإيرادات.",
    "credit_note_items": "بنود إشعارات الدائن (المرتجعات/الخصومات).",
    "credit_notes": "إشعارات الدائن الصادرة للعملاء.",
    "currencies": "العملات المعتمدة في النظام.",
    "currency_rates": "أسعار صرف العملات بحسب التاريخ.",
    "customer_employee": "ربط العملاء بالموظفين/المندوبين المسؤولين عنهم.",
    "customers": "العملاء: البيانات الأساسية والحدود الائتمانية والأرصدة.",
    "cycle_count_items": "بنود الجرد الدوري: الكمية المتوقعة والمعدودة والفرق.",
    "cycle_counts": "عمليات الجرد الدوري للمستودعات.",
    "dashboard_widgets": "عناصر (ودجات) لوحات المعلومات.",
    "dashboards": "لوحات المعلومات المخصصة.",
    "employee_commission_withdrawals": "سحوبات الموظفين من عمولاتهم.",
    "employee_commissions": "عمولات الموظفين/المندوبين على المبيعات.",
    "employees": "بيانات الموظفين والرواتب والارتباط بالمستخدمين.",
    "expenses": "المصاريف التشغيلية.",
    "failed_jobs": "المهام الخلفية التي فشلت (Laravel Queue).",
    "feature_role": "ربط الميزات بالأدوار.",
    "features": "ميزات/صفحات النظام القابلة للتفعيل حسب الدور.",
    "fixed_assets": "الأصول الثابتة والإهلاك.",
    "inquiries": "استفسارات وطلبات الزوار من الموقع.",
    "inquiry_replies": "الردود على الاستفسارات.",
    "integration_settings": "إعدادات التكامل مع الأنظمة الخارجية.",
    "inventory_cost_layers": "طبقات كلفة المخزون (FIFO / متوسط مرجح).",
    "inventory_transfer_items": "بنود التحويلات المخزنية.",
    "inventory_transfers": "التحويلات بين المستودعات.",
    "invoice_items": "بنود الفواتير.",
    "invoices": "فواتير المبيعات: الإجماليات والضرائب والحالة.",
    "job_batches": "دفعات المهام الخلفية (Laravel).",
    "jobs": "طابور المهام الخلفية (Laravel Queue).",
    "journal_entry_headers": "رؤوس القيود اليومية.",
    "journal_entry_lines": "أسطر القيود اليومية (مدين/دائن).",
    "landed_costs": "تكاليف الشحن والجمارك المضافة على كلفة المشتريات.",
    "leave_requests": "طلبات الإجازات.",
    "ledger_accounts": "دليل الحسابات (شجرة الحسابات).",
    "migrations": "سجل ترحيلات قاعدة البيانات (Laravel).",
    "model_has_permissions": "الصلاحيات الممنوحة مباشرة للمستخدمين (Spatie).",
    "model_has_roles": "الأدوار المسندة للمستخدمين (Spatie).",
    "notification_preferences": "تفضيلات استقبال الإشعارات لكل مستخدم.",
    "notification_templates": "قوالب الإشعارات.",
    "notifications": "الإشعارات المرسلة للمستخدمين.",
    "order_channels": "قنوات الطلب (موقع، هاتف، مندوب...).",
    "order_items": "بنود طلبات المتجر.",
    "orders": "طلبات المتجر الإلكتروني.",
    "packing_list_items": "بنود قوائم التعبئة.",
    "packing_lists": "قوائم التعبئة للشحنات.",
    "password_reset_tokens": "رموز إعادة تعيين كلمة المرور.",
    "payment_methods": "طرق الدفع.",
    "payments": "الدفعات المقبوضة من العملاء.",
    "payrolls": "مسيرات الرواتب.",
    "permissions": "الصلاحيات (Spatie).",
    "personal_access_tokens": "رموز الوصول للـ API (Sanctum).",
    "picking_list_items": "بنود قوائم الانتقاء من المستودع.",
    "picking_lists": "قوائم انتقاء البضاعة لتجهيز الطلبات.",
    "price_offer_list_items": "بنود قوائم عروض الأسعار.",
    "price_offer_lists": "قوائم عروض الأسعار.",
    "product_assembly_order_items": "مكونات أوامر التجميع.",
    "product_assembly_orders": "أوامر تجميع المنتجات المركبة.",
    "product_batches": "دفعات/تشغيلات المنتجات وتواريخ الصلاحية.",
    "product_components": "مكونات المنتج المركب (قائمة المواد BOM).",
    "product_serial_numbers": "الأرقام التسلسلية للمنتجات.",
    "product_units": "وحدات المنتج ومعاملات التحويل (قطعة، كرتونة...).",
    "product_variants": "متغيرات المنتج (مقاس، لون...).",
    "product_warehouse_assignments": "ربط المنتجات بالمستودعات وحدود الطلب.",
    "production_orders": "أوامر الإنتاج.",
    "products": "المنتجات: البيانات الأساسية والأسعار والصور.",
    "purchase_order_items": "بنود أوامر الشراء.",
    "purchase_orders": "أوامر الشراء من الموردين.",
    "purchase_receipt_items": "بنود إيصالات الاستلام.",
    "purchase_receipts": "إيصالات استلام البضاعة من الموردين.",
    "purchase_return_items": "بنود مرتجعات المشتريات.",
    "purchase_returns": "مرتجعات المشتريات للموردين.",
    "quote_items": "بنود عروض الأسعار.",
    "quotes": "عروض الأسعار للعملاء.",
    "reorder_alerts": "تنبيهات إعادة الطلب عند انخفاض المخزون.",
    "reports": "التقارير المحفوظة.",
    "reviews": "تقييمات المنتجات.",
    "rma_items": "بنود طلبات الإرجاع.",
    "rma_requests": "طلبات إرجاع البضاعة من العملاء (RMA).",
    "role_has_permissions": "الصلاحيات الممنوحة لكل دور (Spatie).",
    "roles": "الأدوار (Spatie).",
    "sales_contracts": "عقود المبيعات مع العملاء.",
    "sales_order_item_allocations": "حجز/تخصيص كميات بنود أوامر البيع من المستودعات.",
    "sales_order_items": "بنود أوامر البيع.",
    "sales_order_revisions": "سجل تعديلات أوامر البيع.",
    "sales_order_routings": "توجيه أوامر البيع بين الفروع/المستودعات.",
    "sales_order_status_histories": "تاريخ تغيّر حالات أوامر البيع.",
    "sales_orders": "أوامر البيع.",
    "sessions": "جلسات المستخدمين.",
    "settings": "إعدادات النظام والموقع (مفتاح/قيمة).",
    "shipping_manifest_items": "بنود بيانات الشحن.",
    "shipping_manifests": "بيانات (مانيفست) الشحن.",
    "site_visitors": "إحصاءات زيارات الموقع.",
    "special_offers": "العروض الخاصة على المنتجات.",
    "stock_movements": "حركات المخزون (دخول/خروج/تحويل/تسوية) — دفتر الأستاذ المخزني.",
    "subscribers": "المشتركون في النشرة البريدية.",
    "supplier_payments": "الدفعات المسددة للموردين.",
    "suppliers": "الموردون.",
    "tickets": "تذاكر الدعم الفني.",
    "users": "مستخدمو النظام وبيانات الدخول.",
    "visitors": "سجل الزوار.",
    "wallet_transactions": "حركات المحافظ (الصناديق/الأرصدة).",
    "warehouse_bins": "مواقع التخزين داخل المستودع (رف/خانة).",
    "warehouse_inventory": "رصيد كل منتج في كل مستودع.",
    "warehouses": "المستودعات والفروع.",
    "wishlist_items": "قائمة الأمنيات للعملاء.",
    "workflow_executions": "سجل تنفيذ مسارات العمل.",
    "workflow_steps": "خطوات مسارات العمل.",
    "workflows": "مسارات العمل والموافقات الآلية.",
}

names = {t["n"] for t in d["tables"]}
grouped = {n for _, _, ts in GROUPS for n in ts}
other = sorted(names - grouped)
groups = [{"id": g, "label": l, "tables": [t for t in ts if t in names]} for g, l, ts in GROUPS]
if other:
    groups.append({"id": "other", "label": "أخرى", "tables": other})

tables = {}
for t in d["tables"]:
    tables[t["n"]] = {"rows": t["r"], "comment": t["c"] or "", "desc": DESC.get(t["n"], ""), "cols": [], "fks": [], "refs": [], "idx": []}
for c in d["cols"]:
    if c["t"] in tables:
        tables[c["t"]]["cols"].append({k: c[k] for k in ("n", "ty", "nu", "d", "k", "e", "c")})
for f in d["fks"]:
    if f["t"] in tables:
        tables[f["t"]]["fks"].append(f)
    if f["rt"] in tables:
        tables[f["rt"]]["refs"].append(f)
for i in d["idx"]:
    if i["t"] in tables:
        tables[i["t"]]["idx"].append(i)

payload = {
    "db": d["db"],
    "generated": datetime.date.today().isoformat(),
    "groups": groups,
    "tables": tables,
    "stats": {"tables": len(tables), "cols": len(d["cols"]), "fks": len(d["fks"]), "idx": len(d["idx"])},
}
if len(sys.argv) > 4:
    payload["diag"] = json.load(open(sys.argv[4]))
if len(sys.argv) > 5:
    payload["wf"] = json.load(open(sys.argv[5]))
if len(sys.argv) > 6:
    payload["logic"] = json.load(open(sys.argv[6]))
data = json.dumps(payload, ensure_ascii=False).replace("</", "<\\/")
tpl = open(sys.argv[3]).read()
open(out, "w").write(tpl.replace("/*__DATA__*/null", data))
print("written", out, len(data) // 1024, "KB")
