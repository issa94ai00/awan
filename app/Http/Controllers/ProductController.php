<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product)
    {
        abort_unless((int) ($product->is_active ?? 0) === 1, 404);

        $product->load('category');

        // Get product SEO data
        $productSeo = json_decode($product->seo ?? '{}', true);
        
        // Set SEO data for the page
        $page_title = $productSeo['meta_title'] ?? $product->name_ar;
        $page_description = $productSeo['meta_description'] ?? $product->description_ar ?? strip_tags($product->description_en ?? '');
        $page_image = $product->image_main ? asset('storage/' . $product->image_main) : asset('assets/images/hero-bg.jpg');

        $related_products = Product::query()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', 1)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return view('product-details', compact('product', 'related_products', 'page_title', 'page_description', 'page_image'));
    }
}
