<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    /**
     * Helper to retrieve common localized SEO settings.
     */
    private function getCommonSeo(string $locale): array
    {
        $siteName = $locale === 'en' 
            ? (get_setting('site_name_en') ?: 'Awaan Altakadom') 
            : (get_setting('site_name') ?: 'أوان التقدم');
            
        $siteDescription = $locale === 'en'
            ? (get_setting('site_description_en') ?: 'At Awan Al Taqaddam, we offer building supplies that combine global quality with modern design, to be your ideal partner in your construction projects.')
            : (get_setting('site_description') ?: 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.');
            
        $siteKeywords = $locale === 'en'
            ? (get_setting('meta_keywords_en') ?: 'building materials, Syria, Damascus, wholesale construction')
            : (get_setting('meta_keywords') ?: 'أفضل تاجر مواد بناء, أوان التقدم, سوريا, دمشق, مستلزمات بناء');
            
        $ogImageSetting = get_setting('og_image');
        $siteImage = $ogImageSetting 
            ? asset('storage/' . $ogImageSetting) 
            : asset('assets/images/logo.png');
            
        return [$siteName, $siteDescription, $siteKeywords, $siteImage];
    }

    /**
     * Cleans html tags and limits text length for description fields.
     */
    private function cleanString(?string $string, int $limit = 160): string
    {
        if (!$string) {
            return '';
        }
        $string = strip_tags($string);
        $string = str_replace(["\r", "\n", "\t"], ' ', $string);
        $string = preg_replace('/\s+/', ' ', $string);
        $string = trim($string);
        
        if (mb_strlen($string) > $limit) {
            $string = mb_substr($string, 0, $limit - 3) . '...';
        }
        return $string;
    }

    public function home()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = $locale === 'en'
            ? (get_setting('meta_title_en') ?: ($siteName . ' - ' . (get_setting('site_tagline_en') ?: 'Building a Better Tomorrow')))
            : (get_setting('meta_title') ?: ($siteName . ' - ' . (get_setting('site_tagline') ?: 'نبني معاً غد سورية الأجمل')));
            
        $seo_description = $locale === 'en'
            ? (get_setting('meta_description_en') ?: $siteDescription)
            : (get_setting('meta_description') ?: $siteDescription);
            
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        $seo_json_ld = $this->generateOrgJsonLd($siteName, $siteDescription, $seo_image);
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image', 'seo_json_ld'));
    }

    public function vision()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $title = $locale === 'en' ? (get_setting('vision_title_en') ?: 'Our Identity & Vision') : (get_setting('vision_title') ?: 'هويتنا ورؤيتنا');
        $desc = $locale === 'en' ? (get_setting('vision_description_en') ?: $siteDescription) : (get_setting('vision_description') ?: $siteDescription);
        
        $seo_title = $title . ' - ' . $siteName;
        $seo_description = $this->cleanString($desc);
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function about()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $title = $locale === 'en' ? (get_setting('about_title_en') ?: 'About Us') : (get_setting('about_title') ?: 'من نحن');
        $desc = $locale === 'en' ? (get_setting('about_description_en') ?: $siteDescription) : (get_setting('about_description') ?: $siteDescription);
        
        $seo_title = $title . ' - ' . $siteName;
        $seo_description = $this->cleanString($desc);
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function contact()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Contact Us' : 'اتصل بنا') . ' - ' . $siteName;
        $seo_description = $locale === 'en'
            ? 'Contact Awaan Al-Takadom for consultations and supply of premium building materials.'
            : 'تواصل معنا في أوان التقدم للحصول على استشارات وتوريد مستلزمات البناء الفاخرة.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function inquiry()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Send Inquiry' : 'إرسال استفسار') . ' - ' . $siteName;
        $seo_description = $locale === 'en'
            ? 'Send an inquiry about our building materials and construction solutions.'
            : 'أرسل استفساراً حول منتجاتنا ومستلزمات البناء التي نقدمها.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function purchaseRequest()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Purchase Request' : 'طلب شراء') . ' - ' . $siteName;
        $seo_description = $locale === 'en'
            ? 'Submit a purchase request for high-quality building and installation materials.'
            : 'قدم طلب شراء لمستلزمات ومواد البناء والتركيب عالية الجودة.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function customerOrders()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Orders & Invoices' : 'الطلبات والفواتير') . ' - ' . $siteName;
        $seo_description = $locale === 'en' ? 'Track your customer orders and view invoices.' : 'تتبع طلباتك واستعرض فواتير الشراء الخاصة بك.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function featuredProducts()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Featured Products' : 'المنتجات المميزة') . ' - ' . $siteName;
        $seo_description = $locale === 'en' ? 'Explore featured products and construction supplies.' : 'اكتشف المنتجات المميزة ومستلزمات البناء الإنشائية.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function categories()
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $seo_title = ($locale === 'en' ? 'Categories' : 'الفئات') . ' - ' . $siteName;
        $seo_description = $locale === 'en' ? 'Browse our main construction product categories.' : 'تصفح الفئات الرئيسية لمواد البناء ومستلزمات التثبيت.';
        $seo_keywords = $siteKeywords;
        $seo_image = $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function categoryShow($categorySlug)
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $category = Category::where('slug', $categorySlug)->where('is_active', 1)->first();
        if (!$category) {
            return view('vue');
        }
        
        $catName = $locale === 'en' ? ($category->name_en ?: $category->name_ar) : $category->name_ar;
        $catDesc = $locale === 'en' ? ($category->description_en ?: ($category->description_ar ?: $category->description)) : ($category->description_ar ?: $category->description);
        
        $seo_title = ($category->meta_title ?: $catName) . ' - ' . $siteName;
        $seo_description = $this->cleanString($category->meta_description ?: ($catDesc ?: $siteDescription));
        $seo_keywords = $siteKeywords;
        $seo_image = $category->image ? asset('storage/' . $category->image) : $siteImage;
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image'));
    }

    public function productShow($productSlug)
    {
        $locale = app()->getLocale();
        list($siteName, $siteDescription, $siteKeywords, $siteImage) = $this->getCommonSeo($locale);
        
        $product = Product::where('slug', $productSlug)->where('is_active', 1)->first();
        if (!$product) {
            return view('vue');
        }
        
        $prodName = $locale === 'en' ? ($product->name_en ?: $product->name_ar) : $product->name_ar;
        $prodDesc = $locale === 'en' 
            ? ($product->short_description_en ?: ($product->description_en ?: $product->description)) 
            : ($product->short_description_ar ?: ($product->description_ar ?: $product->description));
        
        $seo_title_val = null;
        $seo_desc_val = null;
        if (!empty($product->seo) && is_array($product->seo)) {
            $seo_title_val = $product->seo['meta_title'] ?? null;
            $seo_desc_val = $product->seo['meta_description'] ?? null;
        }
        
        $seo_title = ($seo_title_val ?: $prodName) . ' - ' . $siteName;
        $seo_description = $this->cleanString($seo_desc_val ?: ($prodDesc ?: $siteDescription));
        
        $prodKeywords = $siteKeywords;
        if ($product->brand) {
            $prodKeywords = $product->brand . ', ' . $prodKeywords;
        }
        $seo_keywords = $prodKeywords;
        $seo_image = $product->image_main ? asset('storage/' . $product->image_main) : $siteImage;
        
        $seo_json_ld = $this->generateProductJsonLd($product, $prodName, $seo_description, $seo_image, $siteName);
        
        return view('vue', compact('seo_title', 'seo_description', 'seo_keywords', 'seo_image', 'seo_json_ld'));
    }

    public function catchAll()
    {
        return $this->home();
    }

    private function generateOrgJsonLd(string $name, string $description, string $logo): string
    {
        $data = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => $name,
            "description" => $description,
            "url" => url('/'),
            "logo" => $logo,
            "contactPoint" => [
                "@type" => "ContactPoint",
                "telephone" => get_setting('contact_phone') ?? '00963962889577',
                "email" => get_setting('contact_email') ?? 'awaanaltakadom@gmail.com',
                "contactType" => "customer service",
                "areaServed" => ["SY", "SA"],
                "availableLanguage" => ["Arabic", "English"]
            ],
            "sameAs" => array_values(array_filter([
                get_setting('facebook'),
                get_setting('instagram'),
                get_setting('twitter'),
                get_setting('linkedin'),
                get_setting('youtube')
            ]))
        ];
        
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }

    private function generateProductJsonLd(Product $product, string $name, string $description, string $image, string $brandName): string
    {
        $currency = get_setting('default_currency') ?: 'SAR';
        $data = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => $name,
            "image" => [$image],
            "description" => $description,
            "sku" => $product->sku ?? ("PROD-" . $product->id),
            "mpn" => $product->barcode ?? ("MPN-" . $product->id),
            "brand" => [
                "@type" => "Brand",
                "name" => $product->brand ?: $brandName
            ],
            "offers" => [
                "@type" => "Offer",
                "url" => request()->url(),
                "priceCurrency" => $currency,
                "price" => $product->price ?: "0.00",
                "availability" => $product->in_stock ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
                "itemCondition" => "https://schema.org/NewCondition"
            ]
        ];
        
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}
