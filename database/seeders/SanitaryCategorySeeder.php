<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Sub-categories for the sanitary/plumbing hardware inventory imported from
 * Products.xlsx. Nested under the existing top-level categories: id 11
 * "مواد سباكة وصحية" (plumbing) and id 9 "المواد الاستهلاكية والأدوات" (hardware).
 */
class SanitaryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $parentCategories = [
            [
                'name_ar' => 'المواد الاستهلاكية والأدوات',
                'name_en' => 'Consumable Items & Hardware',
                'slug' => 'consumable-items-hardware',
                'icon' => 'fa-screwdriver-wrench',
                'description' => 'تشكيلة واسعة من الأدوات المهنية والمواد الاستهلاكية.',
                'sort_order' => 1,
                'is_active' => 1,
                'is_featured' => 0,
            ],
            [
                'name_ar' => 'مواد سباكة وصحية',
                'name_en' => 'Plumbing and sanitary materials',
                'slug' => 'plumbing-and-sanitary-materials',
                'icon' => 'fa-folder',
                'description' => 'مجموعة متكاملة من مواد السباكة والصحية عالية الجودة.',
                'sort_order' => 2,
                'is_active' => 1,
                'is_featured' => 0,
            ],
        ];

        foreach ($parentCategories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'parent_id' => null,
                    'product_count' => 0,
                    'description_en' => null,
                    'meta_title' => null,
                    'meta_description' => null,
                ]
            );
        }

        $categories = [
            [
                'name_ar' => 'مفصلات | Hinges',
                'name_en' => 'Hinges',
                'slug' => 'hinges',
                'parent_slug' => 'consumable-items-hardware',
                'sort_order' => 1,
                'product_count' => 12,
            ],
            [
                'name_ar' => 'خلاطات وحنفيات | Faucets & Mixers',
                'name_en' => 'Faucets & Mixers',
                'slug' => 'faucets-and-valves',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 2,
                'product_count' => 78,
            ],
            [
                'name_ar' => 'عدد وأدوات | Tools & Hardware',
                'name_en' => 'Tools & Hardware',
                'slug' => 'tools-and-hardware',
                'parent_slug' => 'consumable-items-hardware',
                'sort_order' => 3,
                'product_count' => 17,
            ],
            [
                'name_ar' => 'اكسسوارات الحمام | Bathroom Accessories',
                'name_en' => 'Bathroom Accessories',
                'slug' => 'bathroom-accessories',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 4,
                'product_count' => 54,
            ],
            [
                'name_ar' => 'أوكار وصمامات | Elbows & Valves',
                'name_en' => 'Elbows & Valves',
                'slug' => 'elbows-and-valves',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 5,
                'product_count' => 43,
            ],
            [
                'name_ar' => 'مستلزمات السباكة العامة | General Plumbing Supplies',
                'name_en' => 'General Plumbing Supplies',
                'slug' => 'general-plumbing-supplies',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 6,
                'product_count' => 7,
            ],
            [
                'name_ar' => 'كراسي الحمام وملحقاتها | Toilet Seats & Accessories',
                'name_en' => 'Toilet Seats & Accessories',
                'slug' => 'toilet-seats-accessories',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 7,
                'product_count' => 8,
            ],
            [
                'name_ar' => 'مواسير ووصلات | Pipes & Fittings',
                'name_en' => 'Pipes & Fittings',
                'slug' => 'pipes-and-fittings',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 8,
                'product_count' => 25,
            ],
            [
                'name_ar' => 'مصافي وريكارات | Strainers & Levelers',
                'name_en' => 'Strainers & Levelers',
                'slug' => 'filters-and-drains',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 9,
                'product_count' => 12,
            ],
            [
                'name_ar' => 'هرابات ومجالي | Floor Traps & Basins',
                'name_en' => 'Floor Traps & Basins',
                'slug' => 'sinks-and-basins',
                'parent_slug' => 'plumbing-and-sanitary-materials',
                'sort_order' => 10,
                'product_count' => 10,
            ],
        ];

        foreach ($categories as $data) {
            $parentId = Category::where('slug', $data['parent_slug'])->value('id');
            unset($data['parent_slug']);

            Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data + [
                    'parent_id' => $parentId,
                    'description' => null,
                    'is_featured' => 0,
                    'description_en' => null,
                    'meta_title' => null,
                    'meta_description' => null,
                ]
            );
        }

        $this->command->info('Seeded sanitary/plumbing sub-categories');
    }
}
