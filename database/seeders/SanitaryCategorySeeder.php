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
        $categories = [
            [
            'name_ar' => 'مفصلات | Hinges',
            'name_en' => 'Hinges',
            'slug' => 'hinges',
            'parent_id' => 9,
            'sort_order' => 1,
            'product_count' => 12,
        ],
        [
            'name_ar' => 'خلاطات وحنفيات | Faucets & Mixers',
            'name_en' => 'Faucets & Mixers',
            'slug' => 'faucets-and-valves',
            'parent_id' => 11,
            'sort_order' => 2,
            'product_count' => 78,
        ],
        [
            'name_ar' => 'عدد وأدوات | Tools & Hardware',
            'name_en' => 'Tools & Hardware',
            'slug' => 'tools-and-hardware',
            'parent_id' => 9,
            'sort_order' => 3,
            'product_count' => 17,
        ],
        [
            'name_ar' => 'اكسسوارات الحمام | Bathroom Accessories',
            'name_en' => 'Bathroom Accessories',
            'slug' => 'bathroom-accessories',
            'parent_id' => 11,
            'sort_order' => 4,
            'product_count' => 54,
        ],
        [
            'name_ar' => 'أوكار وصمامات | Elbows & Valves',
            'name_en' => 'Elbows & Valves',
            'slug' => 'elbows-and-valves',
            'parent_id' => 11,
            'sort_order' => 5,
            'product_count' => 43,
        ],
        [
            'name_ar' => 'مستلزمات السباكة العامة | General Plumbing Supplies',
            'name_en' => 'General Plumbing Supplies',
            'slug' => 'general-plumbing-supplies',
            'parent_id' => 11,
            'sort_order' => 6,
            'product_count' => 7,
        ],
        [
            'name_ar' => 'كراسي الحمام وملحقاتها | Toilet Seats & Accessories',
            'name_en' => 'Toilet Seats & Accessories',
            'slug' => 'toilet-seats-accessories',
            'parent_id' => 11,
            'sort_order' => 7,
            'product_count' => 8,
        ],
        [
            'name_ar' => 'مواسير ووصلات | Pipes & Fittings',
            'name_en' => 'Pipes & Fittings',
            'slug' => 'pipes-and-fittings',
            'parent_id' => 11,
            'sort_order' => 8,
            'product_count' => 25,
        ],
        [
            'name_ar' => 'مصافي وريكارات | Strainers & Levelers',
            'name_en' => 'Strainers & Levelers',
            'slug' => 'filters-and-drains',
            'parent_id' => 11,
            'sort_order' => 9,
            'product_count' => 12,
        ],
        [
            'name_ar' => 'هرابات ومجالي | Floor Traps & Basins',
            'name_en' => 'Floor Traps & Basins',
            'slug' => 'sinks-and-basins',
            'parent_id' => 11,
            'sort_order' => 10,
            'product_count' => 10,
        ],
        ];

        foreach ($categories as $data) {
            $slug = $data['slug'];
            unset($data['product_count'], $data['sort_order']);
            Category::updateOrCreate(['slug' => $slug], $data + [
                'description' => null,
                'is_featured' => 0,
            ]);
        }

        $this->command->info('Seeded sanitary/plumbing sub-categories');
    }
}
