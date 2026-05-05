<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'sort_order' => 1,
                'name_ar' => 'حلول البناء المعمارية | Architectural Building Solutions',
                'name_en' => 'Architectural Building Solutions',
                'description' => 'حلول شاملة للبناء المعماري بما في ذلك فتحات السطح، حواجز الدرج، وأنظمة الحماية.',
                'icon' => 'fa-building',
            ],
            [
                'sort_order' => 2,
                'name_ar' => 'أنظمة إدارة الكابلات | Cable Management Systems',
                'name_en' => 'Cable Management Systems',
                'description' => 'حلول متكاملة لإدارة وتنظيم الكابلات الكهربائية.',
                'icon' => 'fa-network-wired',
            ],
            [
                'sort_order' => 3,
                'name_ar' => 'إكسسوارات البلوك والجص | Blockwork & Plastering Accessories',
                'name_en' => 'Blockwork & Plastering Accessories',
                'description' => 'إكسسوارات متخصصة لأعمال البلوك والجص والتشطيبات.',
                'icon' => 'fa-trowel-bricks',
            ],
            [
                'sort_order' => 4,
                'name_ar' => 'إكسسوارات قوالب الخرسانة | Concrete Formwork Accessories',
                'name_en' => 'Concrete Formwork Accessories',
                'description' => 'معدات وأدوات خاصة بأعمال قوالب الخرسانة.',
                'icon' => 'fa-cube',
            ],
            [
                'sort_order' => 5,
                'name_ar' => 'العزل المائي والحراري | Waterproofing & Thermal Insulation',
                'name_en' => 'Waterproofing & Thermal Insulation',
                'description' => 'مواد العزل المائي والحراري بأعلى معايير الجودة.',
                'icon' => 'fa-shield-alt',
            ],
            [
                'sort_order' => 6,
                'name_ar' => 'مشابك الأنابيب والعلاقات | Pipe Clamps, Hangers & Fixings',
                'name_en' => 'Pipe Clamps, Hangers & Fixings',
                'description' => 'أنظمة تثبيت متكاملة للأنابيب والكوابل.',
                'icon' => 'fa-link',
            ],
            [
                'sort_order' => 7,
                'name_ar' => 'القواطع الجبسية والأسقف المعلقة | Gypsum Partitions & Suspended Ceilings',
                'name_en' => 'Gypsum Partitions & Suspended Ceilings',
                'description' => 'حلول جبسية عصرية للمساحات الداخلية.',
                'icon' => 'fa-border-all',
            ],
            [
                'sort_order' => 8,
                'name_ar' => 'إكسسوارات الكلادينج والواجهات | Cladding & Facade Accessories',
                'name_en' => 'Cladding & Facade Accessories',
                'description' => 'نظام متكامل لتكسية الواجهات بأعلى المعايير.',
                'icon' => 'fa-layer-group',
            ],
            [
                'sort_order' => 9,
                'name_ar' => 'المواد الاستهلاكية والأدوات | Consumable Items & Hardware',
                'name_en' => 'Consumable Items & Hardware',
                'description' => 'تشكيلة واسعة من الأدوات المهنية والمواد الاستهلاكية.',
                'icon' => 'fa-screwdriver-wrench',
            ],
        ];

        foreach ($categories as $data) {
            $slug = Str::slug($data['name_en']);
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name_ar' => $data['name_ar'],
                    'name_en' => $data['name_en'],
                    'description' => $data['description'],
                    'icon' => $data['icon'],
                    'sort_order' => $data['sort_order'],
                    'image' => null,
                    'product_count' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
