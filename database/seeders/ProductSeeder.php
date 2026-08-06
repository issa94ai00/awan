<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => 1,
                'category_id' => 1,
                'name_ar' => 'فتحة الاسطح للطوارئ والصيانة',
                'name_en' => 'Roof Hatch',
                'slug' => 'architectural-building-solutions-roof-hatch',
                'description_ar' => 'فتحة أسطح مصنوعة من الألمنيوم أو الفولاذ المجلفن، توفر وصولاً آمناً وسهلاً إلى الأسطح لأعمال الطوارئ والصيانة والتهوية. تصميم محكم الإغلاق يمنع تسرب المياه والهواء، مع نظام قفل أمان ومفصلات متينة تتحمل الظروف الجوية القاسية.',
                'description_en' => 'Roof hatch made of aluminum or galvanized steel, providing safe and easy access to roofs for emergency, maintenance, and ventilation work. Tight-sealing design prevents water and air leakage, with a safety locking system and durable hinges that withstand harsh weather conditions.',
                'price' => 1981.00,
                'image_main' => 'products/NVn1cQuVX4qEv4GVZu5HKAOL0oovIy6e4Su8sNaA.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 1,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 2,
                'category_id' => 10,
                'name_ar' => 'أنظمة حواجز الدرج',
                'name_en' => 'Handrail Systems',
                'slug' => 'architectural-building-solutions-handrail-systems',
                'description_ar' => 'أنظمة حواجز درج متينة ومصنوعة من مواد عالية الجودة (ستانلس ستيل، حديد، ألمنيوم)، توفر الأمان والثبات على السلالم والمنحدرات. تصميمات عصرية تناسب المباني السكنية والتجارية، مع سهولة التركيب ومقاومة ممتازة للصدأ والتآكل.',
                'description_en' => 'Durable handrail systems made of high-quality materials (stainless steel, iron, aluminum), providing safety and stability on stairs and ramps. Modern designs suitable for residential and commercial buildings, with easy installation and excellent resistance to rust and corrosion.',
                'price' => 2006.00,
                'image_main' => 'products/x410CqLrzhYGNE2EN8kAL3kHyhWpea5jUuSmsADH.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 1,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 3,
                'category_id' => 1,
                'name_ar' => 'نظام مرمى النفايات والبياضات',
                'name_en' => 'Garbage & Linen Chutes',
                'slug' => 'architectural-building-solutions-garbage-linen-chutes',
                'description_ar' => 'نظام مرمى متكامل للنفايات والبياضات، مصنوع من مواد مقاومة للحريق والصدمات، يستخدم في الفنادق والمستشفيات والمباني السكنية. يوفر حلاً نظيفاً وسريعاً لنقل النفايات والغسيل بين الطوابق مع عزل تام للروائح والضوضاء.',
                'description_en' => 'Integrated garbage and linen chute system made of fire and impact-resistant materials, used in hotels, hospitals, and residential buildings. Provides a clean, fast solution for transporting waste and laundry between floors with complete odor and noise isolation.',
                'price' => 3498.00,
                'image_main' => 'products/v7hV5EvHSJIJMJBVsokg5QR9rB6vUjPwqg3ilosc.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 1,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 4,
                'category_id' => 1,
                'name_ar' => 'أرضية مرتفعة للوصول',
                'name_en' => 'Access Raised Floor',
                'slug' => 'architectural-building-solutions-access-raised-floor',
                'description_ar' => 'أرضية مرتفعة قابلة للإزالة تسمح بالوصول السهل إلى الكابلات والأنابيب وأنظمة التبريد تحت الأرض. مثالية لمراكز البيانات وغرف الخوادم والمكاتب التقنية، توفر تهوية ممتازة ومرونة كاملة في إعادة التوزيع.',
                'description_en' => 'Removable raised access floor allowing easy access to cables, pipes, and cooling systems beneath the surface. Ideal for data centers, server rooms, and technical offices, providing excellent ventilation and complete reconfiguration flexibility.',
                'price' => 1894.00,
                'image_main' => 'products/EOm8fJLc0rqWIMQoOUGse7TFZgAbc1qSMfZ5bPuG.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 5,
                'category_id' => 1,
                'name_ar' => 'الخزائن',
                'name_en' => 'Lockers',
                'slug' => 'architectural-building-solutions-lockers',
                'description_ar' => 'خزائن معدنية متينة متعددة الأحجام والألوان، مثالية لتخزين الأغراض الشخصية في المدارس والشركات والصالات الرياضية. تصميم مقاوم للصدمات والصدأ مع أقفال أمان موثوقة وتهوية جيدة.',
                'description_en' => 'Durable metal lockers available in various sizes and colors, ideal for storing personal belongings in schools, offices, and gymnasiums. Impact and rust-resistant design with reliable safety locks and good ventilation.',
                'price' => 905.00,
                'image_main' => 'products/jn48Fb4hSsuFho5skoHtEXmywNBLJRC9VPduGV0y.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 6,
                'category_id' => 10,
                'name_ar' => 'السلالم',
                'name_en' => 'Ladders',
                'slug' => 'architectural-building-solutions-ladders',
                'description_ar' => 'سلالم ألمنيوم ومعدنية عالية الجودة، خفيفة الوزن وقوية التحمل، مثالية للاستخدام في مواقع البناء والمستودعات والمنازل. تصميم مضاد للانزلاق مع أرجل ثابتة توفر لك الأمان والثبات أثناء العمل على الارتفاعات.',
                'description_en' => 'High-quality aluminum and metal ladders, lightweight yet strong, ideal for use on construction sites, warehouses, and homes. Anti-slip design with stable feet provides safety and stability when working at heights.',
                'price' => 188.00,
                'image_main' => 'products/9mR85weeSX19NjdIPlzlgTEs1f2RnYR0F3m9N1Jf.webp',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 7,
                'category_id' => 10,
                'name_ar' => 'المشابك المعدنية (فولاذ / مجلفن)',
                'name_en' => 'Gratings (Steel / Galvanized)',
                'slug' => 'architectural-building-solutions-gratings-steel-galvanized',
                'description_ar' => 'مشابك معدنية (فولاذية ومجلفنة) عالية التحمل، تستخدم لتغطية فتحات التصريف وقنوات الصرف في المصانع والطرقات. تصميم مخرم يسمح بتصريف المياه مع قدرة على تحمل الأحمال الثقيلة ومقاومة ممتازة للصدأ والتآكل.',
                'description_en' => 'Heavy-duty steel and galvanized gratings used for covering drainage openings and channels in factories and roadways. Perforated design allows water drainage while handling heavy loads with excellent resistance to rust and corrosion.',
                'price' => 3783.00,
                'image_main' => 'products/yHj3gyoLCPAZ6iP5rUpKeATFK5e2REPSgfel2DES.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 8,
                'category_id' => 10,
                'name_ar' => 'الحواجز الفولاذية',
                'name_en' => 'Steel Bollards',
                'slug' => 'architectural-building-solutions-steel-bollards',
                'description_ar' => 'حواجز فولاذية متينة وعالية الجودة، مثالية لتنظيم حركة المركبات والمشاة وحماية الأماكن الحيوية. تصميم عصري ومرن للاستخدام الخارجي يتحمل الظروف القاسية ويوفر أماناً طويل الأمد.',
                'description_en' => 'High-quality, durable steel barriers ideal for organizing vehicle and pedestrian traffic and protecting vital areas. A modern, flexible design for outdoor use that withstands harsh conditions and provides long-lasting safety.',
                'price' => 1131.00,
                'image_main' => 'products/USI9kmhhYJYeletqTFjG1T8HxLY8EHxBCkpaAq9O.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 9,
                'category_id' => 10,
                'name_ar' => 'السياجات',
                'name_en' => 'Fencing',
                'slug' => 'architectural-building-solutions-fencing',
                'description_ar' => 'سياجات معدنية متينة عالية الجودة، مثالية للمنازل والفلل والمنشآت التجارية والصناعية لتحديد الحدود وتوفير الأمان والخصوصية. تتميز بمقاومة ممتازة للصدأ والتآكل، سهلة التركيب، مع تصميمات عصرية متنوعة تناسب جميع الاحتياجات.',
                'description_en' => 'High-quality durable metal fencing, ideal for homes, villas, commercial and industrial facilities to define boundaries and provide security and privacy. Features excellent resistance to rust and corrosion, easy installation, with various modern designs to suit all needs.',
                'price' => 1528.00,
                'image_main' => 'products/o0yGyGffyUZAPZ9KesVtPhoQezdVb5op5GBKtuMS.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
            [
                'id' => 10,
                'category_id' => 10,
                'name_ar' => 'البوابات المعدنية',
                'name_en' => 'Metal Gates',
                'slug' => 'architectural-building-solutions-metal-gates',
                'description_ar' => 'بوابات معدنية متينة مصنوعة من الحديد أو الألمنيوم عالي الجودة، مثالية للمداخل الرئيسية والحدائق والمنشآت التجارية. تتميز بمقاومة ممتازة للصدأ والتآكل، تصميمات عصرية وقابلة للتخصيص، مع أنظمة قفل أمان متطورة تدوم طويلاً.',
                'description_en' => 'Durable metal gates made of high-quality iron or aluminum, ideal for main entrances, gardens, and commercial facilities. Feature excellent resistance to rust and corrosion, modern customizable designs, and advanced safety locking systems for long-lasting performance.',
                'price' => 872.00,
                'image_main' => 'products/0dMD7u5enlv3VxbuMUhGwdOo0JkxaJN0uUXXlJ1y.jpg',
                'image_gallery' => '[]',
                'is_active' => 1,
                'in_stock' => 1,
                'is_featured' => 0,
                'views_count' => 0,
                'sort_order' => 0,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['id' => $product['id']],
                $product
            );
        }

        // Update category product counts
        foreach ($products as $product) {
            $category = Category::find($product['category_id']);
            if ($category) {
                $category->update([
                    'product_count' => Product::query()
                        ->where('category_id', $category->id)
                        ->where('is_active', 1)
                        ->count(),
                ]);
            }
        }

        $this->command->info('Created sample products from database.sql');
    }
}
