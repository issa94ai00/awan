<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name_ar' => 'المواد الاستهلاكية والأدوات',
                'name_en' => 'Consumable Items & Hardware',
                'slug' => 'consumable-items-hardware',
                'icon' => 'fa-screwdriver-wrench',
                'description' => 'تشكيلة واسعة من الأدوات المهنية والمواد الاستهلاكية.',
                'product_count' => 0,
                'sort_order' => 1,
                'is_active' => 1,
                'parent_id' => null,
                'description_en' => null,
                'meta_title' => null,
                'meta_description' => null,
                'is_featured' => 0,
            ],
            [
                'name_ar' => 'مواد سباكة وصحية',
                'name_en' => 'Plumbing and sanitary materials',
                'slug' => 'plumbing-and-sanitary-materials',
                'icon' => 'fa-folder',
                'description' => 'مجموعة متكاملة من مواد السباكة والصحية عالية الجودة.',
                'product_count' => 0,
                'sort_order' => 2,
                'is_active' => 1,
                'parent_id' => null,
                'description_en' => null,
                'meta_title' => null,
                'meta_description' => null,
                'is_featured' => 0,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('Seeded plumbing and hardware parent categories');
    }
}
