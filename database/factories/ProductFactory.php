<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $nameAr = fake()->randomElement([
            'مضخة مياه',
            'خلاط حمام',
            'مغسلة',
            'سقف معلق',
            'إكسسوار كلادينج',
            'مثقاب كهربائي',
            'مشبك أنابيب',
            'علاقة معدنية',
            'نظام تثبيت',
            'حل هندسي',
        ]);

        $brand = fake()->randomElement(['Awan', 'Delta', 'Bosch', 'Grohe', 'Makita', 'Stanley', 'Geberit']);
        $model = strtoupper(fake()->bothify('##??-###'));

        return [
            'category_id' => null, // Will be set by seeder or use model's default
            'name_ar' => $nameAr . ' ' . fake()->numberBetween(1, 500),
            'name_en' => fake()->words(3, true),
            'slug' => Str::slug($nameAr . ' ' . fake()->unique()->numberBetween(1, 99999)),
            'description_ar' => fake()->paragraph(),
            'description_en' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 900),
            'image_main' => null,
            'image_gallery' => null,
            'brand' => $brand,
            'model' => $model,
            'in_stock' => fake()->boolean(85),
            'is_featured' => fake()->boolean(20),
            'views_count' => fake()->numberBetween(0, 5000),
            'is_active' => fake()->boolean(95),
        ];
    }
}
