<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\SiteVisitor;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Payment;
use App\Models\PurchaseReceipt;
use App\Models\Payroll;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Product as ProductModel;
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
        // Basic KPIs
        $categoriesCount = Category::where('is_active', 1)->count();
        $productsCount = Product::where('is_active', 1)->count();
        $inquiriesCount = Inquiry::where('status', 'new')->count();
        $visitorsCount = SiteVisitor::whereDate('visit_date', today())->sum('visit_count');

        // ERP KPIs
        $quotesCount = Quote::count();
        $quotesPending = Quote::where('status', Quote::STATUS_DRAFT)->count();
        $salesOrdersCount = SalesOrder::count();
        $salesOrdersPending = SalesOrder::where('status', SalesOrder::STATUS_PENDING)->count();
        $invoicesCount = Invoice::count();
        $invoicesPending = Invoice::where('status', Invoice::STATUS_PENDING)->count();
        $paymentsCount = Payment::count();
        $paymentsTotal = Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount');
        $customersCount = Customer::count();
        $suppliersCount = Supplier::count();
        $payrollsCount = Payroll::count();
        $payrollsTotal = Payroll::where('status', Payroll::STATUS_PAID)->sum('net_salary');
        
        // Stock KPIs
        $lowStockProducts = ProductModel::where('stock_quantity', '<=', 10)->where('is_active', true)->count();
        $totalStockValue = ProductModel::where('is_active', true)->sum(\DB::raw('stock_quantity * price'));
        
        // Financial KPIs
        $totalRevenue = Invoice::where('status', Invoice::STATUS_PAID)->sum('total');
        $totalDue = Invoice::where('status', Invoice::STATUS_PENDING)->sum('due_amount');
        $customerBalances = Customer::sum('balance');
        $supplierBalances = Supplier::sum('balance');

        // Recent Activity
        $latestProducts = Product::latest()->limit(5)->get();
        $latestInquiries = Inquiry::latest()->limit(5)->get();
        $latestQuotes = Quote::latest()->limit(5)->get();
        $latestSalesOrders = SalesOrder::latest()->limit(5)->get();
        $latestPayments = Payment::latest()->limit(5)->get();

        // Chart Data
        $monthlySales = Invoice::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total) as total')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $salesByStatus = SalesOrder::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $stockByCategory = ProductModel::selectRaw('category_id, SUM(stock_quantity) as total')
            ->where('is_active', true)
            ->with('category')
            ->groupBy('category_id')
            ->get();

        return view('admin.dashboard', compact(
            'categoriesCount', 'productsCount', 'inquiriesCount', 'visitorsCount',
            'quotesCount', 'quotesPending', 'salesOrdersCount', 'salesOrdersPending',
            'invoicesCount', 'invoicesPending', 'paymentsCount', 'paymentsTotal',
            'customersCount', 'suppliersCount', 'payrollsCount', 'payrollsTotal',
            'lowStockProducts', 'totalStockValue', 'totalRevenue', 'totalDue',
            'customerBalances', 'supplierBalances',
            'latestProducts', 'latestInquiries', 'latestQuotes', 'latestSalesOrders', 'latestPayments',
            'monthlySales', 'salesByStatus', 'stockByCategory'
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
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
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
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
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
            'stock_quantity' => 'nullable|integer|min:0',
            'image_main' => 'nullable|image|max:2048',
            'image_gallery.*' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
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
        $validated['stock_quantity'] = $request->input('stock_quantity', 0);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        // Handle SEO data (model casts to array, pass array directly)
        if ($request->has('seo')) {
            $seoData = $request->input('seo', []);
            $seoData = array_filter($seoData, fn($v) => !empty($v));
            $validated['seo'] = !empty($seoData) ? $seoData : null;
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
            'stock_quantity' => 'nullable|integer|min:0',
            'image_main' => 'nullable|image|max:2048',
            'image_gallery.*' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
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
        $validated['stock_quantity'] = $request->input('stock_quantity', 0);
        $validated['is_featured'] = $request->boolean('is_featured', false);

        // Handle SEO data (model casts to array, pass array directly)
        if ($request->has('seo')) {
            $seoData = $request->input('seo', []);
            $seoData = array_filter($seoData, fn($v) => !empty($v));
            $validated['seo'] = !empty($seoData) ? $seoData : null;
        }

        // Handle main image removal (without uploading a new one)
        if ($request->input('remove_main_image') === '1') {
            if ($product->image_main) {
                Storage::disk('public')->delete($product->image_main);
            }
            $validated['image_main'] = null;
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
            $existingGallery = array_values($existingGallery);
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
        $galleryImages = json_decode($product->image_gallery ?? '[]', true);
        foreach ($galleryImages as $image) {
            Storage::disk('public')->delete($image);
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
        $availableCurrencies = ['USD', 'EUR', 'SAR', 'AED', 'EGP'];
        $availableLanguages = ['ar', 'en', 'fr'];
        $availableTimezones = ['Asia/Riyadh', 'Asia/Dubai', 'Asia/Amman', 'Africa/Cairo', 'Europe/Istanbul', 'Europe/Paris', 'UTC'];

        $validated = $request->validate([
            'settings.site_name' => 'nullable|string|max:255',
            'settings.site_tagline' => 'nullable|string|max:255',
            'settings.site_description' => 'nullable|string|max:1000',
            'settings.show_site_name' => 'sometimes|boolean',
            'settings.show_product_price' => 'sometimes|boolean',
            'settings.default_currency' => ['nullable', 'in:' . implode(',', $availableCurrencies)],
            'settings.default_language' => ['nullable', 'in:' . implode(',', $availableLanguages)],
            'settings.timezone' => ['nullable', 'in:' . implode(',', $availableTimezones)],
            'settings.contact_phone' => 'nullable|string|max:50',
            'settings.contact_whatsapp' => 'nullable|string|max:50',
            'settings.contact_email' => 'nullable|email|max:255',
            'settings.address' => 'nullable|string|max:1000',
            'settings.working_hours' => 'nullable|string|max:255',
            'settings.facebook' => 'nullable|url|max:255',
            'settings.instagram' => 'nullable|url|max:255',
            'settings.twitter' => 'nullable|url|max:255',
            'settings.youtube' => 'nullable|url|max:255',
            'settings.linkedin' => 'nullable|url|max:255',
            'settings.meta_title' => 'nullable|string|max:255',
            'settings.meta_description' => 'nullable|string|max:1000',
            'settings.meta_keywords' => 'nullable|string|max:500',
            'settings.google_analytics' => 'nullable|string|max:50',
            'settings.email_notifications' => 'sometimes|boolean',
            'settings.sms_notifications' => 'sometimes|boolean',
            'settings.push_notifications' => 'sometimes|boolean',
            'settings.system_notifications' => 'sometimes|boolean',
        ]);

        $data = $request->input('settings', []);

        // Handle boolean/checkbox fields - set to 0 if not present
        $booleanFields = ['show_product_price', 'show_site_name', 'email_notifications', 'sms_notifications', 'push_notifications', 'system_notifications'];
        foreach ($booleanFields as $field) {
            if (!isset($data[$field])) {
                $data[$field] = '0';
            }
        }

        foreach ($data as $key => $value) {
            $this->updateSettingWithAliases($key, $value);
        }

        // Handle file uploads
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('settings', 'public');
            $this->updateSettingWithAliases('logo', $path);
        }

        if ($request->hasFile('favicon')) {
            $path = $request->file('favicon')->store('settings', 'public');
            $this->updateSettingWithAliases('favicon', $path);
        }

        if ($request->hasFile('og_image')) {
            $path = $request->file('og_image')->store('settings', 'public');
            $this->updateSettingWithAliases('og_image', $path);
        }

        return redirect()->route('admin.settings.index')->with('success', 'تم حفظ الإعدادات بنجاح');
    }

    private function updateSettingWithAliases(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $aliasMap = [
            'logo' => ['site_logo'],
            'site_logo' => ['logo'],
            'address' => ['contact_address'],
            'contact_address' => ['address'],
            'facebook' => ['contact_facebook'],
            'contact_facebook' => ['facebook'],
            'instagram' => ['contact_instagram'],
            'contact_instagram' => ['instagram'],
            'twitter' => ['contact_twitter'],
            'contact_twitter' => ['twitter'],
            'youtube' => ['contact_youtube'],
            'contact_youtube' => ['youtube'],
            'linkedin' => ['contact_linkedin'],
            'contact_linkedin' => ['linkedin'],
        ];

        foreach ($aliasMap[$key] ?? [] as $aliasKey) {
            Setting::updateOrCreate(['key' => $aliasKey], ['value' => $value]);
        }
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

    // POS
    public function posIndex()
    {
        $categories = Category::where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->with(['products' => function ($query) {
                $query->where('is_active', 1)->select('id', 'category_id', 'name_ar', 'name_en', 'slug', 'image_main', 'price', 'show_price', 'in_stock');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('admin.pos.index', compact('categories'));
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

    // Stock Alerts
    public function stockAlerts()
    {
        $lowStockProducts = ProductModel::where('stock_quantity', '<=', 10)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        $outOfStockProducts = ProductModel::where('stock_quantity', '<=', 0)
            ->where('is_active', true)
            ->with('category')
            ->get();

        $criticalStockProducts = ProductModel::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 5)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('stock_quantity', 'asc')
            ->get();

        return view('admin.stock-alerts', compact(
            'lowStockProducts',
            'outOfStockProducts',
            'criticalStockProducts'
        ));
    }
}
