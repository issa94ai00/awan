<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        // Middleware to track site visitors
        $this->middleware(function ($request, $next) {
            $ipAddress = $request->ip();
            $userAgent = $request->header('User-Agent');
            $pageUrl = $request->fullUrl();
            $visitDate = now()->toDateString();
            $visitTime = now()->toTimeString();

            // Check if the visitor already exists for today
            $visitor = \App\Models\SiteVisitor::where('ip_address', $ipAddress)
                ->where('visit_date', $visitDate)
                ->first();

            if ($visitor) {
                // Increment visit count
                $visitor->increment('visit_count');
                $visitor->update([
                    'page_url' => $pageUrl,
                    'user_agent' => $userAgent,
                    'visit_time' => $visitTime,
                ]);
            } else {
                // Create a new visitor record
                \App\Models\SiteVisitor::create([
                    'ip_address' => $ipAddress,
                    'visit_count' => 1,
                    'page_url' => $pageUrl,
                    'user_agent' => $userAgent,
                    'visit_date' => $visitDate,
                    'visit_time' => $visitTime,
                ]);
            }

            return $next($request);
        });
        $setting=\App\Models\Setting::all();
        session(['setting' => $setting]); // Store settings in session for global access

    }
    public function index()
    {
        $categories = \App\Models\Category::where('is_active', 1)
            ->withCount(['products as product_count' => function ($query) {
                $query->where('is_active', 1);
            }])
            ->orderBy('sort_order')
            ->limit(10)
            ->get();

        $featured_products = \App\Models\Product::where('is_featured', 1)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();
        return view('welcome', compact('categories', 'featured_products'));
    }

    public function storeInquiry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:50',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'product_id' => 'nullable|exists:products,id',
        ]);

        // Create the inquiry
        $inquiry = \App\Models\Inquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => \App\Models\Inquiry::STATUS_NEW,
            'product_id' => $validated['product_id'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        // If inquiry came from a product page, redirect back there
        if ($request->has('product_id') && $request->product_id) {
            $product = \App\Models\Product::find($request->product_id);
            if ($product) {
                return redirect()->route('product.show', $product)
                    ->with('inquiry_success', 'تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.');
            }
        }

        return redirect()->route('inquiry.create')->with('success', 'تم إرسال استفسارك بنجاح، سنتواصل معك في أقرب وقت ممكن.');
    }

    public function featuredProducts()
    {
        $featured_products = \App\Models\Product::where('is_featured', 1)
            ->where('is_active', 1)
            ->where('in_stock', 1)
            ->with('category')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('featured-products', compact('featured_products'));
    }
}
