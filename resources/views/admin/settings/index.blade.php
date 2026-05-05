@extends('admin.layout')

@section('title', 'الإعدادات')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-cog"></i> الإعدادات</h1>
        <p>إدارة إعدادات الموقع العامة</p>
    </div>
</div>

<div class="form-card">
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-tabs">
            <button type="button" class="tab-btn active" data-tab="general">عام</button>
            <button type="button" class="tab-btn" data-tab="contact">تواصل</button>
            <button type="button" class="tab-btn" data-tab="social">وسائل التواصل</button>
            <button type="button" class="tab-btn" data-tab="seo">SEO</button>
        </div>

        <div class="tab-content active" id="general">
            <div class="form-group">
                <label for="site_name">اسم الموقع</label>
                <input type="text" id="site_name" name="settings[site_name]" value="{{ $settings['site_name'] ?? 'أوان التقدم' }}" class="form-control">
            </div>

            <div class="form-group">
                <label class="d-block mb-2">عرض اسم الموقع في الشعار</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="show_site_name" name="settings[show_site_name]" value="1" {{ ($settings['show_site_name'] ?? '1') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_site_name">
                        إظهار اسم الموقع بجانب الشعار في شريط التنقل
                    </label>
                </div>
                <small class="form-text text-muted d-block">عند إلغاء التفعيل، سيظهر الشعار فقط بدون اسم الموقع</small>
            </div>

            <div class="form-group">
                <label for="site_tagline">شعار الموقع (Tagline)</label>
                <input type="text" id="site_tagline" name="settings[site_tagline]" value="{{ $settings['site_tagline'] ?? 'نبني معاً غد سورية الأجمل' }}" class="form-control" placeholder="شعار قصير يظهر تحت اسم الموقع">
            </div>

            <div class="form-group">
                <label for="site_description">وصف الموقع</label>
                <textarea id="site_description" name="settings[site_description]" rows="3" class="form-control">{{ $settings['site_description'] ?? '' }}</textarea>
            </div>

            <div class="form-group">
                <label for="logo">شعار الموقع</label>
                <input type="file" id="logo" name="logo" class="form-control-file" accept="image/*">
                @if(isset($settings['logo']) && $settings['logo'])
                    <div class="image-preview mt-2">
                        <img src="{{ asset('storage/' . $settings['logo']) }}" alt="Logo" style="max-width: 150px;">
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label for="favicon">Favicon</label>
                <input type="file" id="favicon" name="favicon" class="form-control-file" accept="image/*">
            </div>

            <div class="form-group">
                <label class="d-block mb-2">عرض أسعار المنتجات</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" id="show_product_price" name="settings[show_product_price]" value="1" {{ ($settings['show_product_price'] ?? '1') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="show_product_price">
                        تفعيل عرض الأسعار في صفحات المنتجات
                    </label>
                </div>
                <small class="form-text text-muted d-block">عند إلغاء التفعيل، لن تظهر الأسعار في صفحات المنتجات للزوار</small>
            </div>
        </div>

        <div class="tab-content" id="contact">
            <div class="form-group">
                <label for="contact_phone">رقم الهاتف</label>
                <input type="text" id="contact_phone" name="settings[contact_phone]" value="{{ $settings['contact_phone'] ?? '' }}" class="form-control">
            </div>

            <div class="form-group">
                <label for="contact_whatsapp">رقم الواتساب</label>
                <input type="text" id="contact_whatsapp" name="settings[contact_whatsapp]" value="{{ $settings['contact_whatsapp'] ?? '' }}" class="form-control">
            </div>

            <div class="form-group">
                <label for="contact_email">البريد الإلكتروني</label>
                <input type="email" id="contact_email" name="settings[contact_email]" value="{{ $settings['contact_email'] ?? '' }}" class="form-control">
            </div>

            <div class="form-group">
                <label for="address">العنوان</label>
                <textarea id="address" name="settings[address]" rows="2" class="form-control">{{ $settings['address'] ?? '' }}</textarea>
            </div>

            <div class="form-group">
                <label for="working_hours">ساعات العمل</label>
                <input type="text" id="working_hours" name="settings[working_hours]" value="{{ $settings['working_hours'] ?? '' }}" class="form-control" placeholder="مثال: الأحد - الخميس 9:00 - 17:00">
            </div>
        </div>

        <div class="tab-content" id="social">
            <div class="form-group">
                <label for="facebook">فيسبوك</label>
                <input type="url" id="facebook" name="settings[facebook]" value="{{ $settings['facebook'] ?? '' }}" class="form-control" placeholder="https://facebook.com/...">
            </div>

            <div class="form-group">
                <label for="instagram">انستغرام</label>
                <input type="url" id="instagram" name="settings[instagram]" value="{{ $settings['instagram'] ?? '' }}" class="form-control" placeholder="https://instagram.com/...">
            </div>

            <div class="form-group">
                <label for="twitter">تويتر / X</label>
                <input type="url" id="twitter" name="settings[twitter]" value="{{ $settings['twitter'] ?? '' }}" class="form-control" placeholder="https://twitter.com/...">
            </div>

            <div class="form-group">
                <label for="youtube">يوتيوب</label>
                <input type="url" id="youtube" name="settings[youtube]" value="{{ $settings['youtube'] ?? '' }}" class="form-control" placeholder="https://youtube.com/...">
            </div>

            <div class="form-group">
                <label for="linkedin">لينكدإن</label>
                <input type="url" id="linkedin" name="settings[linkedin]" value="{{ $settings['linkedin'] ?? '' }}" class="form-control" placeholder="https://linkedin.com/...">
            </div>
        </div>

        <div class="tab-content" id="seo">
            <div class="form-group">
                <label for="meta_title">عنوان الموقع (Meta Title)</label>
                <input type="text" id="meta_title" name="settings[meta_title]" value="{{ $settings['meta_title'] ?? '' }}" class="form-control">
            </div>

            <div class="form-group">
                <label for="meta_description">وصف الموقع (Meta Description)</label>
                <textarea id="meta_description" name="settings[meta_description]" rows="3" class="form-control">{{ $settings['meta_description'] ?? '' }}</textarea>
            </div>

            <div class="form-group">
                <label for="meta_keywords">الكلمات المفتاحية (Meta Keywords)</label>
                <input type="text" id="meta_keywords" name="settings[meta_keywords]" value="{{ $settings['meta_keywords'] ?? '' }}" class="form-control" placeholder="افصل بين الكلمات بفاصلة">
            </div>

            <div class="form-group">
                <label for="og_image">صورة Open Graph</label>
                <input type="file" id="og_image" name="og_image" class="form-control-file" accept="image/*">
                <small class="form-text">الصورة التي تظهر عند مشاركة الموقع على وسائل التواصل (1200x630 بكسل)</small>
                @if(isset($settings['og_image']) && $settings['og_image'])
                    <div class="image-preview mt-2">
                        <img src="{{ asset('storage/' . $settings['og_image']) }}" alt="OG Image" style="max-width: 300px;">
                    </div>
                @endif
            </div>

            <div class="form-group">
                <label for="google_analytics">Google Analytics ID</label>
                <input type="text" id="google_analytics" name="settings[google_analytics]" value="{{ $settings['google_analytics'] ?? '' }}" class="form-control" placeholder="G-XXXXXXXXXX">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> حفظ الإعدادات
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;

        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        this.classList.add('active');
        document.getElementById(tabId).classList.add('active');
    });
});
</script>
@endpush
