# توثيق فريق لارافل

هذا التوثيق موجَّه لفريق تطوير الواجهة الخلفية بلغة Laravel الذي يدعم تطبيق Flutter الخاص بمشروع Easy Sell.

## 1. نظرة عامة
- المشروع عبارة عن نظام بيع متكامل (POS) وتجارة إلكترونية.
- الواجهة الأمامية الحالية هي تطبيق Flutter، والواجهة الخلفية يجب أن توفر API RESTful ثابتة.
- يجب أن يكون التصميم متوافقًا مع التوثيق الموجود في `docs/api` و `pos_new_api.md`.

## 2. قاعدة عنوان API
- Base URL افتراضي: `https://your-domain.com/api/v1`
- جميع نقاط النهاية يجب أن تُسجَّل ضمن النطاق `api/v1` ما لم يُذكر خلاف ذلك.
- الحفاظ على التوافق في الترقيم (versioning) مهم لتغيير واجهة البرمجة دون كسر العميل.

## 3. المصادقة والتخويل
- يستخدم النظام Laravel Sanctum للمصادقة عبر API.
- يجب أن يتلقى العميل `Bearer token` في رأس الطلب:

  ```http
  Authorization: Bearer {token}
  ```

- تنتهي صلاحية التوكنات وفق إعدادات Sanctum.
- نقاط النهاية التي تتطلب مستخدمًا مُسجَّلًا يجب أن تعيد HTTP 401 عند توكن مفقود أو غير صالح.

## 4. تنسيق الطلبات والاستجابات
- الطلبات: JSON في الجسم لجميع POST/PUT/PATCH.
- الرؤوس المطلوبة عادة:
  - `Content-Type: application/json`
  - `Accept: application/json`

- تنسيق الاستجابة العام:

  ```json
  {
    "success": true,
    "message": "...",
    "data": { ... }
  }
  ```

- تنسيق خطأ التحقق:

  ```json
  {
    "success": false,
    "message": "Validation Error",
    "data": null,
    "errors": {
      "field_name": ["Error message"]
    }
  }
  ```

## 5. رموز الحالة HTTP
- 200 OK: العملية نجحت.
- 201 Created: تم إنشاء مورد جديد.
- 204 No Content: حذف ناجح أو طلب لا يحتاج محتوى.
- 400 Bad Request: خطأ عام في الطلب.
- 401 Unauthorized: توكن مفقود أو غير صالح.
- 403 Forbidden: مصادقة صحيحة لكن لا يملك صلاحية.
- 404 Not Found: المورد غير موجود.
- 422 Unprocessable Entity: فشل التحقق من الصحة.
- 500 Internal Server Error: خطأ غير متوقع في الخادم.

## 6. تنسيق API والمسارات الأساسية
### 6.1 المصادقة
| طريقة | المسار | الشرح |
|---|---|---|
| POST | `/api/v1/auth/login` | تسجيل دخول المستخدم |
| POST | `/api/v1/auth/register` | تسجيل مستخدم جديد |
| POST | `/api/v1/auth/logout` | تسجيل الخروج وفسخ التوكن |
| GET | `/api/v1/auth/user` | بيانات المستخدم الحالي |
| PUT | `/api/v1/auth/profile` | تحديث بيانات الملف الشخصي |

### 6.2 الشركات
- `POST /api/companies` لتسجيل شركة جديدة
- الحقول الأساسية: `name`, `contact`, `address`, `latitude`, `longitude`
- يجب دعم التحقق بالشكل التالي:
  - `name`: مطلوب، نص، حد أقصى 255
  - `contact`: مطلوب، نص، حد أقصى 255
  - `address`: مطلوب، نص
  - `latitude` و `longitude`: رقم، اختياري

### 6.3 الفواتير (Invoices)
- `GET /api/v1/invoices` لاستعراض الفواتير
- `POST /api/v1/invoices` لإنشاء فاتورة جديدة
- `GET /api/v1/invoices/{id}` لعرض فاتورة مفردة
- `PUT /api/v1/invoices/{id}/status` لتحديث حالة الفاتورة
- `DELETE /api/v1/invoices/{id}` لحذف فاتورة
- `GET /api/v1/invoices/summary/stats` لإحصائيات سريعة

### 6.4 المنتجات والتصنيفات
- `GET /api/v1/products`
- `GET /api/v1/products/{slug}`
- `GET /api/v1/products/{slug}/related`
- `GET /api/v1/categories`
- `GET /api/v1/categories/{slug}`
- `GET /api/v1/categories/{slug}/products`
- `GET /api/v1/search`
- `GET /api/v1/search/suggestions`

### 6.5 استفسارات المستخدمين
- `POST /api/v1/inquiries` لإرسال استفسار جديد
- `GET /api/v1/user/inquiries` لعرض استفسارات المستخدم
- `GET /api/v1/user/inquiries/{id}` لعرض استفسار معين

## 7. قواعد بيانات ونماذج مقترحة
### 7.1 الجداول الرئيسية
- users
- companies
- categories
- products
- invoices
- invoice_items
- payments
- inquiries
- settings

### 7.2 علاقات رئيسية
- المستخدم يمتلك عدة فواتير.
- الشركة يمكن أن ترتبط بعدة مستخدمين أو فواتير حسب التصميم.
- الفاتورة تحتوي على عدة `invoice_items`.
- كل عنصر فاتورة يمكن أن يرتبط بمنتج أو يحمِل اسم منتج يدويًا.

### 7.3 أفضل الممارسات
- استخدم `foreignId()->constrained()->cascadeOnDelete()` للعلاقات المهمة.
- افصل `invoice_items` عن `products` بحيث يدعم حفظ بيانات السعر والكمية حتى بعد حذف المنتج.
- استخدم `softDeletes()` عند كل جدول يحتاج إمكانية استرجاع.
- استخدم حقول `created_by`, `updated_by` عند الحاجة لتتبع التغيرات.

## 8. توجيهات التطوير
### 8.1 المعايير البرمجية
- اتبع معايير PSR-12 للـ PHP.
- استخدم `FormRequest` للتحقق من صحة البيانات و`Resource` لتنسيق الاستجابات.
- استخدم `Repository` أو `Service` طبقيًا إذا أردت فصل المنطق عن الـ Controllers.
- أماكن ملفات مقترحة:
  - Controllers: `app/Http/Controllers/Api/V1`
  - Requests: `app/Http/Requests/Api`
  - Resources: `app/Http/Resources`
  - Models: `app/Models`
  - Services: `app/Services`

### 8.2 الأداء
- استخدم `with()` لتحميل العلاقات المسبقة (eager loading) لتقليل عدد الاستعلامات.
- استخدم ترشيحًا و`pagination` لمجموعات النتائج الكبيرة.
- دعم `page` و `per_page` في الاستعلامات.

### 8.3 أمن البيانات
- تحقق دائمًا من صلاحية المستخدم قبل أي عملية تعديل أو حذف.
- استخدم Gate/Policy للتحكم في الوصول عند الحاجة.
- نظّف المدخلات وراقب الحقول التي تُجرى عليها عمليات بحث ديناميكية.
- تأكد من تهيئة CORS بشكل مناسب للسماح لتطبيق Flutter بالاتصال دون فتح النظام بالكامل.

## 9. نشر وتحديث
### 9.1 أوامر هامة
```bash
php artisan migrate --force
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

### 9.2 خطوات نشر مُقترحة
1. تحديث الكود من مستودع Git.
2. تشغيل `composer install --no-dev --optimize-autoloader`.
3. تشغيل `php artisan migrate --force`.
4. مسح الكاشات (`route`, `config`, `view`).
5. إعادة تشغيل الخدمة أو الـ queue workers إذا لزم الأمر.

## 10. ملاحظات خاصة بتكامل Flutter
- التطبيق ينتظر تنسيق JSON موحّد.
- يجب أن ترجع جميع القيم الأساسية مثل `invoice_number`, `total`, `subtotal`, `status`.
- إذا كانت نقطة النهاية تتطلب مصادقة، يجب أن تُعيد `401` بدلاً من إعادة صفحة HTML.
- إذا كانت هناك حاجة لمرفقات (صور أو ملفات)، فالأفضل تصميم API منفصل يدعم `multipart/form-data`.
- يجب أن يدعم endpoint الشركة إرجاع `latitude` و `longitude` في حال تم التقاطهما من التطبيق.

## 11. مراجع
- `docs/api/company_registration.md`
- `docs/api_docs_for_Flutter.md`
- `pos_new_api.md`

---

هذا المستند يهدف إلى إعطاء فريق Laravel خطوطًا عامة واضحة وسهلة التطبيق، ويمكن تحديثه لاحقًا مع إضافة مزيد من التفاصيل التقنية أو سيناريوهات الأعمال المحددة.