# وثائق API الكاملة

## نظرة عامة
هذه الوثيقة توضح جميع نقاط النهاية (endpoints) المتاحة في API الخاص بتطبيق Awan، مع توضيح طرق الاتصال، المصادقة، وأشكال الاستجابات الشائعة.

## عنوان الأساس
```
https://your-domain.com/api/v1
```
> في بيئة التطوير قد يكون العنوان `http://localhost:8000/api/v1` أو حسب إعدادات السيرفر المحلي.

## المصادقة
- يستخدم Laravel Sanctum للمصادقة.
- أضف رأس HTTP التالي إلى الطلبات المحمية:
```
Authorization: Bearer {token}
```

## صيغة الاستجابة العامة
الاستجابات الناجحة:
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... }
}
```

أخطاء التحقق:
```json
{
  "success": false,
  "message": "Validation failed",
  "data": null,
  "errors": {
    "field_name": ["Error message"]
  }
}
```

أخطاء عامة:
```json
{
  "success": false,
  "message": "Error message",
  "data": null
}
```

## قواعد عامة
- جميع الموارد العامة تعود للحالة `is_active = 1` فقط.
- يتم تحديد الفئات والمنتجات باستخدام `slug` في المسار.
- يمكن استخدام معاملات الاستعلام (query params) مثل `page`, `per_page`, `search`, `sort_by`, و `sort_order` في العديد من النقاط.

---

## 1. واجهات عامة للزوار (بدون مصادقة)

### 1.1 الصفحة الرئيسية
```
GET /home
```
استرجاع بيانات الصفحة الرئيسية مثل الفئات والمنتجات المميزة.

### 1.2 المنتجات المميزة
```
GET /featured-products
```
قائمة المنتجات المميزة مع ترقيم الصفحات.

### 1.3 الفئات
```
GET /categories
```
استرجاع جميع الفئات النشطة.

### 1.4 تفاصيل فئة
```
GET /categories/{category}
```
حيث `{category}` هو slug الفئة.

### 1.5 منتجات الفئة
```
GET /categories/{category}/products
```
قائمة المنتجات ضمن فئة معينة.

### 1.6 قائمة المنتجات
```
GET /products
```
دعم فلترة بواسطة:
- `category_id`
- `featured`
- `in_stock`
- `search`
- `sort_by`
- `sort_order`
- `page`

### 1.7 تفاصيل منتج
```
GET /products/{product}
```
حيث `{product}` هو slug المنتج.

### 1.8 منتجات ذات صلة
```
GET /products/{product}/related
```

### 1.9 البحث
```
GET /search
```
- `q` (مطلوب): نص البحث.

### 1.10 اقتراحات البحث
```
GET /search/suggestions
```
- `q` (مطلوب): نص جزئي لاقتراحات البحث.

### 1.11 إنشاء استفسار جديد
```
POST /inquiries
```

**جسم الطلب**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "subject": "استفسار عن المنتج",
  "message": "أريد معرفة المزيد عن هذا المنتج",
  "product_id": 1
}
```

---

## 2. المصادقة

### 2.1 تسجيل مستخدم جديد
```
POST /auth/register
```

**جسم الطلب**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "phone": "+1234567890"
}
```

### 2.2 تسجيل الدخول
```
POST /auth/login
```

**جسم الطلب**:
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

### 2.3 تسجيل الخروج
```
POST /auth/logout
```
- يتطلب مصادقة.

### 2.4 بيانات المستخدم الحالي
```
GET /auth/user
```
- يتطلب مصادقة.

### 2.5 تحديث الملف الشخصي
```
PUT /auth/profile
```
- يتطلب مصادقة.

**جسم الطلب**:
```json
{
  "name": "John Smith",
  "email": "johnsmith@example.com",
  "phone": "+1234567890"
}
```

### 2.6 تغيير كلمة المرور
```
POST /auth/change-password
```
- يتطلب مصادقة.

**جسم الطلب**:
```json
{
  "current_password": "oldpassword",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

---

## 3. واجهات محمية تتطلب مصادقة

> جميع المسارات التالية تتطلب رأس المصادقة:
> `Authorization: Bearer {token}`

### 3.1 رفع وحذف ملفات
```
POST /upload
DELETE /upload
```
- رفع ملف جديد.
- حذف ملف موجود باستخدام معرف الملف أو مسار الحذف ضمن جسم الطلب.

### 3.2 إدارة المنتجات (admin)
```
GET /admin/products
GET /admin/products/{product}
POST /admin/products
PUT /admin/products/{product}
DELETE /admin/products/{product}
```
- GET supports search, filter, pagination.
- POST و PUT يدعمان بيانات المنتج مثل الاسم ثنائي اللغة، السعر، المخزون، الفئة، الحالة.

### 3.3 إدارة الفئات (admin)
```
GET /admin/categories
GET /admin/categories/{category}
POST /admin/categories
PUT /admin/categories/{category}
DELETE /admin/categories/{category}
```
- يستخدم `slug` أو معرف الفئة في المسار.

### 3.4 الموردون (admin)
```
GET /admin/suppliers
POST /admin/suppliers
GET /admin/suppliers/{supplier}
PUT /admin/suppliers/{supplier}
DELETE /admin/suppliers/{supplier}
```

### 3.5 الموظفون (admin)
```
GET /admin/employees
POST /admin/employees
GET /admin/employees/{employee}
PUT /admin/employees/{employee}
DELETE /admin/employees/{employee}
```

### 3.6 الحضور (admin)
```
GET /admin/attendance
POST /admin/attendance
GET /admin/attendance/{attendance}
PUT /admin/attendance/{attendance}
DELETE /admin/attendance/{attendance}
```

### 3.7 طلبات الإجازات (admin)
```
GET /admin/leave-requests
POST /admin/leave-requests
GET /admin/leave-requests/{leaveRequest}
PUT /admin/leave-requests/{leaveRequest}
DELETE /admin/leave-requests/{leaveRequest}
```

### 3.8 أوامر الشراء (admin)
```
GET /admin/purchase-orders
POST /admin/purchase-orders
GET /admin/purchase-orders/{order}
PUT /admin/purchase-orders/{order}
DELETE /admin/purchase-orders/{order}
```

### 3.9 حركات المخزون (admin)
```
GET /admin/inventory/movements
POST /admin/inventory/movements
```

### 3.10 الحسابات العامة (admin)
```
GET /admin/accounting/ledger-accounts
POST /admin/accounting/ledger-accounts
GET /admin/accounting/ledger-accounts/{ledgerAccount}
PUT /admin/accounting/ledger-accounts/{ledgerAccount}
DELETE /admin/accounting/ledger-accounts/{ledgerAccount}

GET /admin/accounting/journal-entries
POST /admin/accounting/journal-entries
GET /admin/accounting/trial-balance
```

### 3.11 استفسارات المستخدم الشخصية
```
GET /user/inquiries
GET /user/inquiries/{inquiry}
```

### 3.12 الفواتير
```
GET /invoices
POST /invoices
GET /invoices/{invoice}
PUT /invoices/{invoice}/status
DELETE /invoices/{invoice}
GET /invoices/summary/stats
```

### 3.13 إحصائيات لوحة التحكم
```
GET /dashboard/stats
```

### 3.14 نظام POS
```
GET /pos/options
GET /pos/products/lookup
GET /pos/customers
POST /pos/customers
PUT /pos/customers/{customer}
DELETE /pos/customers/{customer}
GET /pos/customers/{customer}
```

### 3.15 شكاوى وتذاكر العملاء
```
GET /tickets
POST /tickets
GET /tickets/{ticket}
PUT /tickets/{ticket}
DELETE /tickets/{ticket}
```

### 3.16 الشركات
```
POST /companies
```

### 3.17 الإنتاج والتصنيع
```
GET /production
POST /production
GET /production/{productionOrder}
PUT /production/{productionOrder}
DELETE /production/{productionOrder}
PUT /production/{productionOrder}/status
GET /production/stats
```

### 3.18 عروض الأسعار (Quotes)
```
GET /quotes
POST /quotes
GET /quotes/{quote}
PUT /quotes/{quote}
DELETE /quotes/{quote}
POST /quotes/{quote}/convert-to-sales-order
```

### 3.19 طلبات البيع (Sales Orders)
```
GET /sales-orders
POST /sales-orders
GET /sales-orders/{salesOrder}
PUT /sales-orders/{salesOrder}
DELETE /sales-orders/{salesOrder}
POST /sales-orders/{salesOrder}/convert-to-invoice
```

### 3.20 المدفوعات
```
GET /payments
POST /payments
GET /payments/{payment}
PUT /payments/{payment}
DELETE /payments/{payment}
```

### 3.21 إيصالات الشراء (Purchase Receipts)
```
GET /purchase-receipts
POST /purchase-receipts
GET /purchase-receipts/{receipt}
PUT /purchase-receipts/{receipt}
DELETE /purchase-receipts/{receipt}
```

### 3.22 الرواتب
```
GET /payrolls
POST /payrolls
GET /payrolls/{payroll}
PUT /payrolls/{payroll}
DELETE /payrolls/{payroll}
POST /payrolls/auto-generate
```

---

## 4. أمثلة على طلبات وإنشاءات بيانات

### 4.1 إنشاء فاتورة
```
POST /invoices
```

**جسم الطلب**:
```json
{
  "customer_name": "John Doe",
  "customer_phone": "+1234567890",
  "customer_address": "123 Main St",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 500.00
    }
  ],
  "tax": 50.00,
  "discount": 0.00,
  "notes": "تسليم خلال يومين"
}
```

### 4.2 تحديث حالة الفاتورة
```
PUT /invoices/{invoice}/status
```

**جسم الطلب**:
```json
{
  "status": "paid"
}
```

### 4.3 إنشاء منتج (admin)
```
POST /admin/products
```

**جسم الطلب المتوقع**:
```json
{
  "name_ar": "لابتوب ديل",
  "name_en": "Dell Laptop",
  "slug": "dell-laptop",
  "description_ar": "...",
  "description_en": "...",
  "price": 2500.00,
  "sale_price": 2200.00,
  "show_price": true,
  "in_stock": true,
  "stock_quantity": 10,
  "category_id": 1,
  "is_featured": true,
  "is_active": true
}
```

### 4.4 إنشاء فئة (admin)
```
POST /admin/categories
```

**جسم الطلب المتوقع**:
```json
{
  "name_ar": "إلكترونيات",
  "name_en": "Electronics",
  "slug": "electronics",
  "description": "الأجهزة الإلكترونية والكمبيوترات",
  "icon": "electronics-icon.svg",
  "image": "url-or-filename",
  "is_active": true,
  "sort_order": 1
}
```

### 4.5 إنشاء عميل POS
```
POST /pos/customers
```

**جسم الطلب المتوقع**:
```json
{
  "name": "Ahmed Ali",
  "phone": "+201234567890",
  "email": "ahmed@example.com",
  "address": "Cairo, Egypt",
  "notes": "عميل تجاري"
}
```

```

**جسم الطلب**:
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "company": "ABC Co.",
  "address": "123 Main St",
  "source": "POS",
  "status": "active",
  "notes": "Customer prefers evening delivery"
}
```

### 4.6 إنشاء طلبية إنتاج
```
POST /production
```

**جسم الطلب**:
```json
{
  "product_id": 5,
  "quantity": 100,
  "start_date": "2024-01-15",
  "end_date": "2024-01-20",
  "cost": 5000.00,
  "notes": "Priority order for client"
}
```

---

## 5. معلمات استعلام شائعة
- `page`: رقم الصفحة
- `per_page`: عدد العناصر لكل صفحة
- `search`: نص بحث عام
- `sort_by`: حقل الترتيب
- `sort_order`: `asc` أو `desc`
- `status`: حالة المورد
- `customer_id`, `product_id`, `category_id`: مرشحات حسب المعرف

---

## 6. رموز الحالة
| الرمز | الوصف |
|------|--------|
| 200 | ناجح |
| 201 | تم الإنشاء |
| 400 | طلب غير صالح |
| 401 | غير مصرح |
| 403 | ممنوع |
| 404 | غير موجود |
| 422 | خطأ في التحقق |
| 500 | خطأ في الخادم |

---

## 7. ملاحظات إضافية
- جميع طلبات API تعود بصيغة JSON.
- تأكد من إرسال رأس `Content-Type: application/json` في الطلبات التي تحتوي على جسم JSON.
- في حال استخدام رفع ملفات، قد يستعمل API نوع `multipart/form-data` حسب حالة الرفع.
- رابط نهاية غير معروف يرجع رسالة خطأ JSON عامة.
