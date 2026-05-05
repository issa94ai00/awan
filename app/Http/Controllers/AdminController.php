<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\SiteVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct()
    {
        // Middleware can be added here for admin authentication
        // $this->middleware('auth');
        // $this->middleware('admin');
    }

    // Dashboard
    public function dashboard()
    {
        $categoriesCount = Category::where('is_active', 1)->count();
        $productsCount = Product::where('is_active', 1)->count();
        $inquiriesCount = Inquiry::where('status', 'new')->count();
        $visitorsCount = SiteVisitor::whereDate('visit_date', today())->sum('visit_count');

        $latestProducts = Product::latest()->limit(5)->get();
        $latestInquiries = Inquiry::latest()->limit(5)->get();

        return view('admin.dashboard', compact(
            'categoriesCount', 'productsCount', 'inquiriesCount', 'visitorsCount',
            'latestProducts', 'latestInquiries'
        ));
    }

    // Categories
    public function categoriesIndex(Request $request)
    {
        $query = Category::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name_ar', 'like', '%' . $request->search . '%')
                  ->orWhere('name_en', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $categories = $query->orderBy('sort_order')->paginate(15);

        return view('admin.categories.index', compact('categories'));
    }

    public function categoriesCreate()
    {
        return view('admin.categories.form');
    }

    public function categoriesStore(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'تم إضافة الفئة بنجاح');
    }

    public function categoriesEdit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function categoriesUpdate(Request $request, Category $category)
    {
        // Debug: Log request data
        \Log::info('Category update request received', [
            'category_id' => $category->id,
            'category_slug' => $category->slug,
            'request_data' => $request->all()
        ]);

        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug,' . $category->id,
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        \Log::info('Validation passed', ['validated' => $validated]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $validated['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($validated);

        \Log::info('Category updated successfully');

        return redirect()->route('admin.categories.index')->with('success', 'تم تحديث الفئة بنجاح');
    }

    public function categoriesDestroy(Category $category)
    {
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'تم حذف الفئة بنجاح');
    }

    // Products
    public function productsIndex(Request $request)
    {
        $query = Product::query()->with('category');

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name_ar', 'like', '%' . $request->search . '%')
                  ->orWhere('name_en', 'like', '%' . $request->search . '%')
                  ->orWhere('brand', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        $products = $query->latest()->paginate(15);
        $categories = Category::where('is_active', 1)->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function productsCreate()
    {
        $categories = Category::where('is_active', 1)->get();
        return view('admin.products.form', compact('categories'));
    }

    public function productsStore(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'image_main' => 'nullable|image|max:2048',
            'image_gallery.*' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            'in_stock' => 'boolean',
            'is_featured' => 'boolean',
            'show_price' => 'boolean',
            'seo' => 'nullable|array',
            'seo.meta_title' => 'nullable|string|max:255',
            'seo.meta_description' => 'nullable|string|max:500',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['in_stock'] = $request->boolean('in_stock', true);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        // Handle SEO data - convert to JSON
        if ($request->has('seo')) {
            $seoData = $request->input('seo', []);
            $seoData = array_filter($seoData, fn($v) => !empty($v));
            $validated['seo'] = !empty($seoData) ? json_encode($seoData) : null;
        }

        if ($request->hasFile('image_main')) {
            $validated['image_main'] = $request->file('image_main')->store('products', 'public');
        }

        // Handle gallery images
        if ($request->hasFile('image_gallery')) {
            $gallery = [];
            foreach ($request->file('image_gallery') as $image) {
                $gallery[] = $image->store('products/gallery', 'public');
            }
            $validated['image_gallery'] = json_encode($gallery);
        }

        $validated['show_price'] = $request->boolean('show_price', true);

        Product::create($validated);

        return redirect()->route('admin.products.index')->with('success', 'تم إضافة المنتج بنجاح');
    }

    public function productsEdit(Product $product)
    {
        $categories = Category::where('is_active', 1)->get();
        return view('admin.products.form', compact('product', 'categories'));
    }

    public function productsUpdate(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug,' . $product->id,
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'image_main' => 'nullable|image|max:2048',
            'image_gallery.*' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
            'in_stock' => 'boolean',
            'is_featured' => 'boolean',
            'show_price' => 'boolean',
            'seo' => 'nullable|array',
            'seo.meta_title' => 'nullable|string|max:255',
            'seo.meta_description' => 'nullable|string|max:500',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name_en']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['in_stock'] = $request->boolean('in_stock', true);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        // Handle SEO data - convert to JSON
        if ($request->has('seo')) {
            $seoData = $request->input('seo', []);
            $seoData = array_filter($seoData, fn($v) => !empty($v));
            $validated['seo'] = !empty($seoData) ? json_encode($seoData) : null;
        }

        if ($request->hasFile('image_main')) {
            if ($product->image_main) {
                Storage::disk('public')->delete($product->image_main);
            }
            $validated['image_main'] = $request->file('image_main')->store('products', 'public');
        }

        // Handle gallery images
        $existingGallery = json_decode($product->image_gallery ?? '[]', true);
        
        // Handle gallery image removal
        if ($request->has('remove_gallery_images')) {
            $imagesToRemove = $request->input('remove_gallery_images', []);
            foreach ($imagesToRemove as $imageToRemove) {
                if (($key = array_search($imageToRemove, $existingGallery)) !== false) {
                    Storage::disk('public')->delete($imageToRemove);
                    unset($existingGallery[$key]);
                }
            }
            $existingGallery = array_values($existingGallery); // Re-index array
        }
        
        // Add new gallery images
        if ($request->hasFile('image_gallery')) {
            foreach ($request->file('image_gallery') as $image) {
                $existingGallery[] = $image->store('products/gallery', 'public');
            }
        }
        
        $validated['image_gallery'] = json_encode(array_values($existingGallery));

        $validated['show_price'] = $request->boolean('show_price', true);

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'تم تحديث المنتج بنجاح');
    }

    public function productsDestroy(Product $product)
    {
        if ($product->image_main) {
            Storage::disk('public')->delete($product->image_main);
        }
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'تم حذف المنتج بنجاح');
    }

    // Inquiries
    public function inquiriesIndex(Request $request)
    {
        $query = Inquiry::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('subject', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $inquiries = $query->latest()->paginate(15);

        return view('admin.inquiries.index', compact('inquiries'));
    }

    public function inquiriesShow(Inquiry $inquiry)
    {
        // Mark as read if new
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'read']);
        }

        return view('admin.inquiries.show', compact('inquiry'));
    }

    public function inquiriesUpdate(Request $request, Inquiry $inquiry)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,read,replied',
        ]);

        $inquiry->update($validated);

        return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'تم تحديث الحالة');
    }

    public function inquiriesDestroy(Inquiry $inquiry)
    {
        $inquiry->delete();
        return redirect()->route('admin.inquiries.index')->with('success', 'تم حذف الاستفسار');
    }

    // Settings
    public function settingsIndex()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function settingsUpdate(Request $request)
    {
        $data = $request->input('settings', []);

        // Handle boolean/checkbox fields - set to 0 if not present
        $booleanFields = ['show_product_price', 'show_site_name'];
        foreach ($booleanFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '0';
            }
        }

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => $path]);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'favicon'], ['value' => $path]);
        }

        if ($request->hasFile('og_image')) {
            $path = $request->file('og_image')->store('settings', 'public');
            Setting::updateOrCreate(['key' => 'og_image'], ['value' => $path]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    // Visitors
    public function visitorsIndex(Request $request)
    {
        $query = \App\Models\Visitor::query();

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('visited_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('visited_at', '<=', $request->date_to);
        }

        // Filter by device type
        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        // Filter by browser
        if ($request->filled('browser')) {
            $query->where('browser', $request->browser);
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Search by IP or page URL
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('ip_address', 'like', '%' . $request->search . '%')
                  ->orWhere('page_url', 'like', '%' . $request->search . '%')
                  ->orWhere('browser', 'like', '%' . $request->search . '%');
            });
        }

        $visitors = $query->latest('visited_at')->paginate(20);

        // Statistics
        $stats = [
            'today' => \App\Models\Visitor::todayCount(),
            'week' => \App\Models\Visitor::weekCount(),
            'month' => \App\Models\Visitor::monthCount(),
            'total' => \App\Models\Visitor::totalCount(),
            'unique_today' => \App\Models\Visitor::uniqueTodayCount(),
        ];

        // Charts data
        $dailyStats = \App\Models\Visitor::dailyStats(30);
        $deviceStats = \App\Models\Visitor::byDeviceType();
        $browserStats = \App\Models\Visitor::byBrowser();
        $topPages = \App\Models\Visitor::topPages(10);

        // Filter options
        $deviceTypes = \App\Models\Visitor::select('device_type')->distinct()->whereNotNull('device_type')->pluck('device_type');
        $browsers = \App\Models\Visitor::select('browser')->distinct()->whereNotNull('browser')->pluck('browser');
        $countries = \App\Models\Visitor::select('country')->distinct()->whereNotNull('country')->pluck('country');

        return view('admin.visitors.index', compact(
            'visitors', 'stats', 'dailyStats', 'deviceStats', 'browserStats', 'topPages',
            'deviceTypes', 'browsers', 'countries'
        ));
    }

    // Profile
    public function profileEdit()
    {
        $user = auth()->user();
        return view('admin.profile.edit', compact('user'));
    }

    public function profileUpdate(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:password',
            'password' => 'nullable|min:8|confirmed',
        ]);

        // Update name and email
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        // Update password if provided
        if (!empty($validated['password'])) {
            if (!\Hash::check($validated['current_password'], $user->password)) {
                return back()->with('error', 'كلمة المرور الحالية غير صحيحة');
            }
            $user->password = \Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('admin.profile.edit')->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
