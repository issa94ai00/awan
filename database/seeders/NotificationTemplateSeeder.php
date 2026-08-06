<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Order Notifications
            [
                'template_key' => 'order_confirmed',
                'name' => 'Order Confirmed',
                'name_ar' => 'تأكيد الطلب',
                'subject' => 'Your order #{order_number} has been confirmed',
                'subject_ar' => 'تم تأكيد طلبك #{order_number}',
                'body' => 'Dear customer, your order #{order_number} has been confirmed and is being processed. Thank you for your purchase!',
                'body_ar' => 'عزيزي العميل، تم تأكيد طلبك #{order_number} وهو قيد المعالجة. شكراً لشرائك!',
                'type' => 'email',
                'variables' => ['order_number', 'customer_name'],
                'is_active' => true,
            ],
            [
                'template_key' => 'order_shipped',
                'name' => 'Order Shipped',
                'name_ar' => 'شحن الطلب',
                'subject' => 'Your order #{order_number} has been shipped',
                'subject_ar' => 'تم شحن طلبك #{order_number}',
                'body' => 'Great news! Your order #{order_number} has been shipped and is on its way to you. Tracking number: {tracking_number}',
                'body_ar' => 'أخبار رائعة! تم شحن طلبك #{order_number} وهو في طريقه إليك. رقم التتبع: {tracking_number}',
                'type' => 'email',
                'variables' => ['order_number', 'tracking_number', 'customer_name'],
                'is_active' => true,
            ],
            [
                'template_key' => 'order_delivered',
                'name' => 'Order Delivered',
                'name_ar' => 'تسليم الطلب',
                'subject' => 'Your order #{order_number} has been delivered',
                'subject_ar' => 'تم تسليم طلبك #{order_number}',
                'body' => 'Your order #{order_number} has been successfully delivered. We hope you enjoy your purchase!',
                'body_ar' => 'تم تسليم طلبك #{order_number} بنجاح. نأمل أن تستمتع بشرائك!',
                'type' => 'email',
                'variables' => ['order_number', 'customer_name'],
                'is_active' => true,
            ],
            [
                'template_key' => 'order_cancelled',
                'name' => 'Order Cancelled',
                'name_ar' => 'إلغاء الطلب',
                'subject' => 'Your order #{order_number} has been cancelled',
                'subject_ar' => 'تم إلغاء طلبك #{order_number}',
                'body' => 'Your order #{order_number} has been cancelled. Reason: {reason}. If you have any questions, please contact us.',
                'body_ar' => 'تم إلغاء طلبك #{order_number}. السبب: {reason}. إذا كان لديك أي أسئلة، يرجى الاتصال بنا.',
                'type' => 'email',
                'variables' => ['order_number', 'reason', 'customer_name'],
                'is_active' => true,
            ],
            // Inventory Notifications
            [
                'template_key' => 'low_stock_alert',
                'name' => 'Low Stock Alert',
                'name_ar' => 'تنبيه انخفاض المخزون',
                'subject' => 'Low Stock Alert: {product_name}',
                'subject_ar' => 'تنبيه انخفاض المخزون: {product_name}',
                'body' => 'Product {product_name} is running low on stock. Current stock: {current_stock}, Minimum stock: {min_stock}. Please reorder soon.',
                'body_ar' => 'المنتج {product_name} يعاني من انخفاض في المخزون. المخزون الحالي: {current_stock}، الحد الأدنى: {min_stock}. يرجى إعادة الطلب قريباً.',
                'type' => 'email',
                'variables' => ['product_name', 'current_stock', 'min_stock'],
                'is_active' => true,
            ],
            [
                'template_key' => 'out_of_stock',
                'name' => 'Out of Stock',
                'name_ar' => 'نفاد المخزون',
                'subject' => 'Out of Stock: {product_name}',
                'subject_ar' => 'نفاد المخزون: {product_name}',
                'body' => 'Product {product_name} is now out of stock. Please restock immediately.',
                'body_ar' => 'المنتج {product_name} نفد من المخزون. يرجى إعادة التخزين فوراً.',
                'type' => 'email',
                'variables' => ['product_name'],
                'is_active' => true,
            ],
            // Warehouse Notifications
            [
                'template_key' => 'cycle_count_required',
                'name' => 'Cycle Count Required',
                'name_ar' => 'مطلوب جرد دوري',
                'subject' => 'Cycle Count Required for Zone {zone}',
                'subject_ar' => 'مطلوب جرد دوري للمنطقة {zone}',
                'body' => 'A cycle count is required for zone {zone} in warehouse {warehouse_name}. Please schedule the count.',
                'body_ar' => 'مطلوب جرد دوري للمنطقة {zone} في المستودع {warehouse_name}. يرجى جدولة الجرد.',
                'type' => 'email',
                'variables' => ['zone', 'warehouse_name'],
                'is_active' => true,
            ],
            // System Notifications
            [
                'template_key' => 'welcome_email',
                'name' => 'Welcome Email',
                'name_ar' => 'بريد الترحيب',
                'subject' => 'Welcome to Our Platform!',
                'subject_ar' => 'مرحباً بك في منصتنا!',
                'body' => 'Dear {name}, welcome to our platform! We are excited to have you with us. If you have any questions, feel free to reach out.',
                'body_ar' => 'عزيزي {name}، مرحباً بك في منصتنا! نحن سعداء بانضمامك إلينا. إذا كان لديك أي أسئلة، لا تتردد في التواصل معنا.',
                'type' => 'email',
                'variables' => ['name'],
                'is_active' => true,
            ],
            [
                'template_key' => 'password_reset',
                'name' => 'Password Reset',
                'name_ar' => 'إعادة تعيين كلمة المرور',
                'subject' => 'Reset Your Password',
                'subject_ar' => 'إعادة تعيين كلمة المرور',
                'body' => 'Click the link below to reset your password: {reset_link}. This link will expire in 1 hour.',
                'body_ar' => 'انقر على الرابط أدناه لإعادة تعيين كلمة المرور: {reset_link}. ستنتهي صلاحية هذا الرابط خلال ساعة واحدة.',
                'type' => 'email',
                'variables' => ['reset_link'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['template_key' => $template['template_key']],
                $template
            );
        }

        $this->command->info('Created notification templates');
    }
}
