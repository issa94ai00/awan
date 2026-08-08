# دليل تدقيق وتحسين قاعدة البيانات - MySQL ERP
# Database Audit and Optimization Guide - MySQL ERP

## نظرة عامة / Overview

هذا الدليل يوفر حزمة شاملة لتدقيق وتحسين قاعدة بيانات MySQL لنظام الـ ERP، مع التركيز على سلامة البيانات، أداء الاستعلامات، وحماية المخزون.

This guide provides a comprehensive package for auditing and optimizing the MySQL database for the ERP system, focusing on data integrity, query performance, and inventory protection.

---

## الملفات المضمنة / Included Files

### سكربتات التدقيق / Audit Scripts

1. **01_check_engine_and_collation.sql**
   - فحص محرك التخزين (InnoDB vs MyISAM)
   - التحقق من الترميز (utf8mb4)
   - تحديد الجداول التي تحتاج تحويل

2. **02_check_foreign_keys.sql**
   - فحص جميع المفاتيح الخارجية
   - التحقق من قواعد الحذف والتحديث
   - اكتشاف المفاتيح المفقودة أو غير الصحيحة

3. **03_check_indexes.sql**
   - تحليل الفهارس الحالية
   - اكتشاف الفهارس المكررة أو غير المستخدمة
   - توصيات بفهارس جديدة للأداء

4. **04_data_integrity_checks.sql**
   - اكتشاف السجلات المتيمة (orphaned records)
   - فحص عدم الاتساق في البيانات
   - التحقق من الأرصدة السالبة

### سكربتات الترحيل / Migration Scripts

5. **2026_08_07_000005_optimize_database_structure.sql**
   - تحسين هيكل قاعدة البيانات
   - إضافة فهارس للأداء
   - تحديث قواعد المفاتيح الخارجية

6. **2026_08_07_000006_add_stock_balance_triggers.sql**
   - إضافة Triggers لمنع الأرصدة السالبة
   - حساب تلقائي للكمية المتاحة
   - حماية من حذف البيانات بالخطأ

### ملفات التكوين والصيانة / Configuration & Maintenance

7. **00_audit_report_template.md**
   - قالب تقرير التدقيق
   - لتوثيق نتائج التدقيق

8. **05_mysql_configuration_recommendations.cnf**
   - إعدادات MySQL المحسنة
   - مخصص لأنظمة ERP الكبيرة

9. **06_maintenance_plan.sh**
   - سكربت صيانة آلي
   - مهام يومية/أسبوعية/شهرية

---

## خطوات التنفيذ / Implementation Steps

### المرحلة 1: التدقيق الأولي / Initial Audit

```bash
# 1. فحص محرك التخزين
mysql -u root -p your_database < database/audits/01_check_engine_and_collation.sql

# 2. فحص المفاتيح الخارجية
mysql -u root -p your_database < database/audits/02_check_foreign_keys.sql

# 3. فحص الفهارس
mysql -u root -p your_database < database/audits/03_check_indexes.sql

# 4. فحص سلامة البيانات
mysql -u root -p your_database < database/audits/04_data_integrity_checks.sql
```

### المرحلة 2: تحليل النتائج / Analyze Results

- راجع نتائج كل سكربت
- حدد المشاكل الحرجة (الأولوية عالية)
- وثق النتائج في قالب التقرير

### المرحلة 3: تنفيذ التحسينات / Apply Optimizations

```bash
# 1. تشغيل سكربت الترحيل
mysql -u root -p your_database < database/migrations/2026_08_07_000005_optimize_database_structure.sql

# 2. إضافة Triggers
mysql -u root -p your_database < database/migrations/2026_08_07_000006_add_stock_balance_triggers.sql
```

### المرحلة 4: تحديث إعدادات MySQL / Update MySQL Configuration

```bash
# 1. نسخ ملف التكوين
cp database/audits/05_mysql_configuration_recommendations.cnf /etc/mysql/my.cnf

# 2. تعديل الإعدادات حسب حجم الخادم
# راجع التعليقات في الملف للإعدادات المناسبة

# 3. إعادة تشغيل MySQL
sudo systemctl restart mysql
```

### المرحلة 5: إعداد الصيانة الآلية / Setup Automated Maintenance

```bash
# 1. جعل السكربت قابلاً للتنفيذ
chmod +x database/audits/06_maintenance_plan.sh

# 2. إضافة إلى Cron
# يومياً الساعة 2 صباحاً
0 2 * * * /path/to/database/audits/06_maintenance_plan.sh daily

# أسبوعياً يوم الأحد الساعة 3 صباحاً
0 3 * * 0 /path/to/database/audits/06_maintenance_plan.sh weekly

# شهرياً أول يوم من الشهر الساعة 4 صباحاً
0 4 1 * * /path/to/database/audits/06_maintenance_plan.sh monthly
```

---

## المشاكل الشائعة والحلول / Common Issues & Solutions

### مشكلة: CASCADE بدلاً من RESTRICT

**الأعراض:**
- حذف غير مقصود للمنتجات مع مخزون
- فقدان بيانات المخزون

**الحل:**
```sql
-- تغيير قاعدة الحذف
ALTER TABLE warehouse_inventory 
DROP FOREIGN KEY warehouse_inventory_warehouse_id_foreign,
ADD CONSTRAINT warehouse_inventory_warehouse_id_foreign 
FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) 
ON DELETE RESTRICT ON UPDATE CASCADE;
```

### مشكلة: الأرصدة السالبة

**الأعراض:**
- كميات سالبة في جدول المخزون
- تقارير غير دقيقة

**الحل:**
- تشغيل سكربت Triggers
- إصلاح البيانات الموجودة:
```sql
UPDATE warehouse_inventory
SET quantity = 0, available_quantity = 0
WHERE quantity < 0;
```

### مشكلة: بطء الاستعلامات

**الأعراض:**
- استعلامات بطيئة على جدول المخزون
- تقارير تأخذ وقتاً طويلاً

**الحل:**
- إضافة الفهارس المقترحة
- تحديث إحصائيات الجدول:
```sql
ANALYZE TABLE warehouse_inventory;
```

---

## مراقبة الأداء / Performance Monitoring

### مؤشرات الأداء الرئيسية / Key Performance Indicators

1. **Buffer Pool Hit Ratio**
```sql
SHOW STATUS LIKE 'Innodb_buffer_pool_read%';
-- يجب أن يكون > 99%
```

2. **Slow Queries**
```sql
SHOW STATUS LIKE 'Slow_queries';
-- يجب أن يكون قليلاً أو صفر
```

3. **Table Locks**
```sql
SHOW STATUS LIKE 'Table_locks%';
-- مراقبة الأقفال الطويلة
```

4. **Disk I/O**
```sql
SHOW STATUS LIKE 'Innodb_data%';
-- مراقبة عمليات القراءة/الكتابة
```

---

## النسخ الاحتياطي والاستعادة / Backup & Recovery

### النسخ الاحتياطي اليومي / Daily Backup

```bash
mysqldump -u root -p your_database \
  --single-transaction \
  --routines \
  --triggers \
  your_database | gzip > backup_$(date +%Y%m%d).sql.gz
```

### الاستعادة / Restore

```bash
gunzip < backup_20260807.sql.gz | mysql -u root -p your_database
```

---

## التحقق من التغييرات / Verification

### بعد الترحيل / After Migration

```sql
-- فحص الفهارس الجديدة
SHOW INDEX FROM warehouse_inventory;

-- فحص Triggers
SHOW TRIGGERS LIKE 'warehouse_inventory';

-- اختبار Trigger (يجب أن يفشل)
INSERT INTO warehouse_inventory (warehouse_id, product_id, quantity, reserved_quantity, available_quantity)
VALUES (1, 1, -10, 0, -10);
```

---

## دعم الطوارئ / Emergency Support

### إذا فشل الترحيل / If Migration Fails

1. **استعادة النسخ الاحتياطي**
```bash
mysql -u root -p your_database < backup_before_migration.sql
```

2. **التحقق من الأخطاء**
```bash
tail -100 /var/log/mysql/error.log
```

3. **تشغيل سكربت الطوارئ**
```bash
./database/audits/06_maintenance_plan.sh emergency
```

---

## الموارد الإضافية / Additional Resources

### وثائق MySQL الرسمية
- https://dev.mysql.com/doc/refman/8.0/en/

### أدوات التشخيص
- MySQL Workbench
- Percona Toolkit
- pt-query-digest

### المراقبة
- Prometheus + Grafana
- MySQL Enterprise Monitor

---

## الجدول الزمني الموصى به / Recommended Timeline

| المرحلة | المدة | المهام |
|--------|------|-------|
| التدقيق | يوم واحد | تشغيل جميع سكربتات التدقيق |
| التحليل | نصف يوم | مراجعة النتائج وتحديد الأولويات |
| الترحيل | يوم واحد | تنفيذ سكربتات الترحيل |
| الاختبار | نصف يوم | اختبار التغييرات في staging |
| الإنتاج | نصف يوم | النشر في بيئة الإنتاج |
| المراقبة | مستمر | مراقبة الأداء بعد النشر |

---

## التواصل والدعم / Contact & Support

للأسئلة أو المشاكل:
- راجع سجلات MySQL في `/var/log/mysql/`
- راجع سجلات الصيانة في `/var/log/mysql/maintenance/`
- راجع الوثائق التقنية في `docs/`

---

**تاريخ الإنشاء:** 7 أغسطس 2026
**الإصدار:** 1.0
**الحالة:** جاهز للاستخدام
