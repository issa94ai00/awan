<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\Review;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;

class FlutterEcommerceSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo user for Flutter app
        $flutterUser = User::firstOrCreate(
            ['email' => 'flutter@demo.com'],
            [
                'name' => 'Flutter Demo User',
                'password' => bcrypt('password123!'),
                'phone' => '+1234567890',
                'is_pro' => true,
                'pro_label' => 'Gold',
                'notifications_enabled' => true,
            ]
        );

        // Create addresses for the user
        Address::factory(3)->create(['user_id' => $flutterUser->id]);

        // Create payment methods for the user
        PaymentMethod::factory(2)->create(['user_id' => $flutterUser->id]);

        // Create reviews for some products
        $products = Product::take(10)->get();
        foreach ($products as $product) {
            Review::factory()->create([
                'user_id' => $flutterUser->id,
                'product_id' => $product->id,
            ]);
        }

        // Add some products to wishlist
        $wishlistProducts = Product::take(5)->get();
        foreach ($wishlistProducts as $product) {
            WishlistItem::firstOrCreate([
                'user_id' => $flutterUser->id,
                'product_id' => $product->id,
            ]);
        }
    }
}
