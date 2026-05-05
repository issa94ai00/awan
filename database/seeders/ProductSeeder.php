<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // A. ARCHITECTURAL BUILDING SOLUTIONS
            'architectural-building-solutions' => [
                ['name_en' => 'Roof Hatch', 'name_ar' => 'فتحة السطح', 'image_main' => 'products/c8j7fSJ4eKkU5v8f7u3J9.jpg'],
                ['name_en' => 'Handrail Systems', 'name_ar' => 'أنظمة حواجز الدرج', 'image_main' => 'products/Wx2n9QjL4vK8p5R7t3H6.jpg'],
                ['name_en' => 'Garbage & Linen Chutes', 'name_ar' => 'ماسورات القمامة والمفروشات', 'image_main' => null],
                ['name_en' => 'Access Raised Floor', 'name_ar' => 'أرضية مرتفعة للوصول', 'image_main' => null],
                ['name_en' => 'Lockers', 'name_ar' => 'الخزائن', 'image_main' => 'products/Dj4h7Wk2mP9n5L8vB3Q6.jpg'],
                ['name_en' => 'Ladders', 'name_ar' => 'السلالم', 'image_main' => null],
                ['name_en' => 'Gratings (Steel / Galvanized)', 'name_ar' => 'المشابك المعدنية (فولاذ / مجلفن)', 'image_main' => null],
                ['name_en' => 'Steel Bollards', 'name_ar' => 'الحواجز الفولاذية', 'image_main' => null],
                ['name_en' => 'Fencing', 'name_ar' => 'السياجات', 'image_main' => null],
                ['name_en' => 'Metal Gates', 'name_ar' => 'البوابات المعدنية', 'image_main' => null],
                ['name_en' => 'Entrance Mats', 'name_ar' => 'دواسات المداخل', 'image_main' => null],
                ['name_en' => 'Cubicle Toilet Partitions', 'name_ar' => 'حواجز المراحيض', 'image_main' => null],
                ['name_en' => 'Expansion Joint Systems', 'name_ar' => 'أنظمة مفاصل التمدد', 'image_main' => null],
                ['name_en' => 'Stair Nosing', 'name_ar' => 'أنف الدرج', 'image_main' => null],
                ['name_en' => 'Tile & Carpet Trim', 'name_ar' => 'حواف البلاط والسجاد', 'image_main' => null],
                ['name_en' => 'Corner Guard (Impact Protection)', 'name_ar' => 'حماية الزوايا (حماية من الصدمات)', 'image_main' => null],
                ['name_en' => 'Wall Guard (Impact Protection)', 'name_ar' => 'حماية الجدران (حماية من الصدمات)', 'image_main' => null],
                ['name_en' => 'Rubber Corner Guard (Impact Protection)', 'name_ar' => 'حماية زوايا المطاط (حماية من الصدمات)', 'image_main' => null],
                ['name_en' => 'Rubber Wall Guard (Impact Protection)', 'name_ar' => 'حماية جدران المطاط (حماية من الصدمات)', 'image_main' => null],
                ['name_en' => 'Dock Fender (D Fender)', 'name_ar' => 'حاجز الرصيف (حاجز D)', 'image_main' => null],
                ['name_en' => 'Speed Humps', 'name_ar' => 'مطبات السرعة', 'image_main' => null],
                ['name_en' => 'Rubber Wheel Stopper', 'name_ar' => 'موقف العجلات المطاطي', 'image_main' => null],
                ['name_en' => 'Movement Joint Covers', 'name_ar' => 'أغطية مفاصل الحركة', 'image_main' => null],
            ],

            // B. CABLE MANAGEMENT SYSTEMS
            'cable-management-systems' => [
                ['name_en' => 'Cable Tray', 'name_ar' => 'حامل الكابلات'],
                ['name_en' => 'Cable Trunking', 'name_ar' => 'أنبوب الكابلات'],
                ['name_en' => 'Cable Ladder & Basket Tray', 'name_ar' => 'سلم الكابلات وحامل السلة'],
                ['name_en' => 'Control Panel Boxes', 'name_ar' => 'صناديق لوحة التحكم'],
                ['name_en' => 'Electrical Boxes & EMT Conduits', 'name_ar' => 'الصناديق الكهربائية وأنابيب EMT'],
                ['name_en' => 'UPVC Conduits', 'name_ar' => 'أنابيب UPVC'],
            ],

            // C. BLOCKWORK & PLASTERING ACCESSORIES
            'blockwork-plastering-accessories' => [
                ['name_en' => 'Lintel Block Ties', 'name_ar' => 'روابط البلوك العلوي'],
                ['name_en' => 'Block Reinforcement (Ladder & Mesh)', 'name_ar' => 'تقوية البلوك (سلم وشبكة)'],
                ['name_en' => 'Corner Bead', 'name_ar' => 'زاوية الحافة'],
                ['name_en' => 'Corner Mesh', 'name_ar' => 'شبكة الزاوية'],
                ['name_en' => 'Plaster Stop Bead', 'name_ar' => 'حافة نهاية الجص'],
                ['name_en' => 'Control Joint', 'name_ar' => 'مفصل التحكم'],
                ['name_en' => 'Architrave Bead', 'name_ar' => 'حافة الإطار'],
                ['name_en' => 'Expanded Metal Lath', 'name_ar' => 'شبكة معدنية موسعة'],
                ['name_en' => 'Coil Lath', 'name_ar' => 'شبكة لفائف'],
                ['name_en' => 'Rib Lath', 'name_ar' => 'شبكة أضلاع'],
                ['name_en' => 'Hy-Rib (Reinforcement Sheet)', 'name_ar' => 'هاي-ريب (ورقة تقوية)'],
            ],

            // D. CONCRETE FORMWORK ACCESSORIES
            'concrete-formwork-accessories' => [
                ['name_en' => 'Plywood (Plywood Timber)', 'name_ar' => 'الخشب الرقائقي (خشب رقائقي)'],
                ['name_en' => 'Tie Rods', 'name_ar' => 'قضبان الربط'],
                ['name_en' => 'Wing Nuts', 'name_ar' => 'صواميل الجناح'],
                ['name_en' => 'Rib Washers', 'name_ar' => 'حشوات الأضلاع'],
                ['name_en' => 'Waterstop', 'name_ar' => 'مانع الماء'],
                ['name_en' => 'PVC Pipe', 'name_ar' => 'أنبوب PVC'],
                ['name_en' => 'PVC Coil', 'name_ar' => 'لفائف PVC'],
                ['name_en' => 'Binding Wire', 'name_ar' => 'سلك الربط'],
                ['name_en' => 'C-Clamp', 'name_ar' => 'مشبك C'],
                ['name_en' => 'Rapid Clamp', 'name_ar' => 'مشبك سريع'],
                ['name_en' => 'Long Nut (Connector)', 'name_ar' => 'صامولة طويلة (موصل)'],
            ],

            // E. WATERPROOFING & THERMAL INSULATION
            'waterproofing-thermal-insulation' => [
                ['name_en' => 'Bitumen Membrane', 'name_ar' => 'غشاء البيتومين'],
                ['name_en' => 'Liquid Membrane', 'name_ar' => 'الغشاء السائل'],
                ['name_en' => 'Filler Board', 'name_ar' => 'لوحة الحشو'],
                ['name_en' => 'Protection Board', 'name_ar' => 'لوحة الحماية'],
                ['name_en' => 'Polypropylene Board', 'name_ar' => 'لوحة البولي بروبيلين'],
                ['name_en' => 'Geotextile Fabric', 'name_ar' => 'قماش الجيوتكستايل'],
                ['name_en' => 'Polyethylene Flashing', 'name_ar' => 'حاجز البولي إيثيلين'],
                ['name_en' => 'Aluminum Flashing', 'name_ar' => 'حاجز الألمنيوم'],
                ['name_en' => 'Hammer Anchor', 'name_ar' => 'مرساة المطرقة'],
                ['name_en' => 'Drill Bits', 'name_ar' => 'لقينات الحفر'],
                ['name_en' => 'PVC Waterstop', 'name_ar' => 'مانع ماء PVC'],
                ['name_en' => 'Extruded Polystyrene (XPS)', 'name_ar' => 'بوليسترين مبثوق (XPS)'],
                ['name_en' => 'Expanded Polystyrene (EPS)', 'name_ar' => 'بوليسترين موسع (EPS)'],
                ['name_en' => 'Rock Wool', 'name_ar' => 'الصوف الصخري'],
            ],

            // F. PIPE CLAMPS, HANGERS & FIXINGS
            'pipe-clamps-hangers-fixings' => [
                ['name_en' => 'Strap Clamp', 'name_ar' => 'مشبك الشريط'],
                ['name_en' => 'U-Clamp', 'name_ar' => 'مشبك U'],
                ['name_en' => 'Pipe Clamp', 'name_ar' => 'مشبك الأنابيب'],
                ['name_en' => 'Swivel Clamp', 'name_ar' => 'مشبك دوار'],
                ['name_en' => 'Clevis Clamp', 'name_ar' => 'مشبك كليفيس'],
                ['name_en' => 'Pipe Hanger', 'name_ar' => 'حامل الأنابيب'],
                ['name_en' => 'Channel', 'name_ar' => 'القناة'],
                ['name_en' => 'Beam Clamp', 'name_ar' => 'مشبك العارضة'],
                ['name_en' => 'Bolt Clamp', 'name_ar' => 'مشبك البراغي'],
            ],

            // G. GYPSUM PARTITIONS & SUSPENDED CEILINGS
            'gypsum-partitions-suspended-ceilings' => [
                ['name_en' => 'Gypsum Board', 'name_ar' => 'لوح الجبس'],
                ['name_en' => 'Cement Board', 'name_ar' => 'لوح الأسمنت'],
                ['name_en' => 'Gypsum Putty', 'name_ar' => 'معجون الجبس'],
                ['name_en' => 'Stud & Runner', 'name_ar' => 'الدعامات والقواعد'],
                ['name_en' => 'Joint Tape', 'name_ar' => 'شريط المفصل'],
                ['name_en' => 'Wall Angle', 'name_ar' => 'زاوية الجدار'],
                ['name_en' => 'Furring Channel', 'name_ar' => 'قناة الفورينج'],
                ['name_en' => 'Channel Clamp', 'name_ar' => 'مشبك القناة'],
                ['name_en' => 'C-Bracket', 'name_ar' => 'قوس C'],
                ['name_en' => 'Wire Connector', 'name_ar' => 'موصل الأسلاك'],
                ['name_en' => 'Adjustable Spring Clip', 'name_ar' => 'مشبك زنبركي قابل للتعديل'],
                ['name_en' => 'Rod Clip', 'name_ar' => 'مشبك القضيب'],
                ['name_en' => 'Threaded Rod Hanger', 'name_ar' => 'حامل القضيب الم threaded'],
                ['name_en' => 'Drop-in Anchor', 'name_ar' => 'مرساة الإسقاط'],
                ['name_en' => 'G-Corner Tape (Corner Tape)', 'name_ar' => 'شريط زاوية G (شريط الزاوية)'],
            ],

            // H. CLADDING & FACADE ACCESSORIES
            'cladding-facade-accessories' => [
                ['name_en' => 'Unistrut Channel', 'name_ar' => 'قناة يونيسترات'],
                ['name_en' => 'Threaded Rod', 'name_ar' => 'القضيب الم threaded'],
                ['name_en' => 'Spring Nut', 'name_ar' => 'صامولة زنبركية'],
                ['name_en' => 'Bracket', 'name_ar' => 'القوس'],
                ['name_en' => 'Flat Anchor', 'name_ar' => 'مرساة مسطحة'],
                ['name_en' => 'Hex Head Bolt', 'name_ar' => 'برغي برأس سداسي'],
                ['name_en' => 'Through Bolt', 'name_ar' => 'برغي التثبيت'],
            ],

            // I. CONSUMABLE ITEMS & HARDWARE
            'consumable-items-hardware' => [
                ['name_en' => 'Hand Tools', 'name_ar' => 'الأدوات اليدوية'],
                ['name_en' => 'Common Nails', 'name_ar' => 'المسامير العادية'],
                ['name_en' => 'Concrete Nails', 'name_ar' => 'مسامير الخرسانة'],
                ['name_en' => 'Burlap (Hessian Cloth)', 'name_ar' => 'الخيش (قماش الهيسيان)'],
                ['name_en' => 'Tie Wire', 'name_ar' => 'سلك الربط'],
                ['name_en' => 'Binding Wire', 'name_ar' => 'سلك التربيط'],
            ],
        ];

        foreach ($products as $category_slug => $items) {
            $category = Category::query()->where('slug', $category_slug)->first();
            
            if (!$category) {
                continue;
            }

            foreach ($items as $index => $item) {
                $product_slug = $category_slug . '-' . Str::slug($item['name_en']);
                
                Product::updateOrCreate(
                    ['slug' => $product_slug],
                    [
                        'category_id' => $category->id,
                        'name_ar' => $item['name_ar'],
                        'name_en' => $item['name_en'],
                        'description_ar' => 'منتج عالي الجودة من ' . $category->name_ar,
                        'description_en' => 'High quality product from ' . $category->name_en,
                        'price' => rand(50, 5000),
                        'image_main' => $item['image_main'] ?? null,
                        'image_gallery' => null,
                        'brand' => null,
                        'model' => null,
                        'is_active' => true,
                        'in_stock' => true,
                        'is_featured' => $index < 3,
                        'views_count' => 0,
                    ]
                );
            }

            $category->update([
                'product_count' => Product::query()
                    ->where('category_id', $category->id)
                    ->where('is_active', 1)
                    ->count(),
            ]);
        }
    }
}
