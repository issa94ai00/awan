@extends('layout')

@section('title', 'استفسار')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>استفسار</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>استفسار</span>
        </div>
    </div>
</section>

<section class="contact-section fade-up" id="inquiry">
    <div class="container">
        <div class="section-header">
            <h2>أرسل استفسارك</h2>
            <p>فريقنا جاهز للإجابة على جميع أسئلتك حول منتجاتنا وخدماتنا</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 10px; margin-bottom: 30px; text-align: center;">
                <i class="fas fa-check-circle" style="margin-left: 10px;"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 10px; margin-bottom: 30px;">
                <ul style="margin: 0; padding-right: 20px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="contact-wrapper" style="grid-template-columns: 1fr 2fr;">
            <div class="contact-info">
                <h3>لماذا تتواصل معنا؟</h3>
                
                <div class="inquiry-reasons">
                    <div class="reason-item">
                        <i class="fas fa-question-circle"></i>
                        <div>
                            <h4>استفسار عن منتج</h4>
                            <p>احصل على معلومات تفصيلية عن أي منتج</p>
                        </div>
                    </div>

                    <div class="reason-item">
                        <i class="fas fa-calculator"></i>
                        <div>
                            <h4>طلب عرض سعر</h4>
                            <p>احصل على عرض سعر مخصص لمشروعك</p>
                        </div>
                    </div>

                    <div class="reason-item">
                        <i class="fas fa-truck"></i>
                        <div>
                            <h4>التوصيل والشحن</h4>
                            <p>استفسر عن خيارات التوصيل والمواعيد</p>
                        </div>
                    </div>

                    <div class="reason-item">
                        <i class="fas fa-cogs"></i>
                        <div>
                            <h4>الدعم الفني</h4>
                            <p>احصل على استشارة فنية متخصصة</p>
                        </div>
                    </div>
                </div>

                <div class="social-links" style="margin-top: 30px; padding-top: 30px; border-top: 1px solid var(--bg-gray);">
                    <h4>أو تواصل مباشرة</h4>
                    <div class="quick-buttons" style="margin-top: 15px;">
                        <a href="https://wa.me/<?php echo get_setting('contact_whatsapp') ?? '963900000000'; ?>" class="quick-btn whatsapp" target="_blank" style="padding: 10px 20px; font-size: 0.9rem;">
                            <i class="fab fa-whatsapp"></i>
                            <span>واتساب</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="contact-form-wrapper">
                <h3>نموذج الاستفسار</h3>
                <form class="contact-form" action="{{ route('inquiry.store') }}" method="POST">
                    @csrf
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label for="name">الاسم الكامل <span style="color: #dc3545;">*</span></label>
                            <input type="text" id="name" name="name" required placeholder="أدخل اسمك الكامل" value="{{ old('name') }}">
                        </div>

                        <div class="form-group">
                            <label for="phone">رقم الهاتف <span style="color: #dc3545;">*</span></label>
                            <input type="tel" id="phone" name="phone" required placeholder="+963 ..." value="{{ old('phone') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">البريد الإلكتروني (اختياري)</label>
                        <input type="email" id="email" name="email" placeholder="example@email.com" value="{{ old('email') }}">
                    </div>

                    <div class="form-group">
                        <label for="subject">موضوع الاستفسار <span style="color: #dc3545;">*</span></label>
                        <select id="subject" name="subject" required>
                            <option value="">اختر موضوع الاستفسار</option>
                            <option value="product_inquiry" {{ old('subject') == 'product_inquiry' ? 'selected' : '' }}>استفسار عن منتج</option>
                            <option value="price_quote" {{ old('subject') == 'price_quote' ? 'selected' : '' }}>طلب عرض سعر</option>
                            <option value="delivery" {{ old('subject') == 'delivery' ? 'selected' : '' }}>التوصيل والشحن</option>
                            <option value="technical_support" {{ old('subject') == 'technical_support' ? 'selected' : '' }}>الدعم الفني</option>
                            <option value="partnership" {{ old('subject') == 'partnership' ? 'selected' : '' }}>شراكة تجارية</option>
                            <option value="other" {{ old('subject') == 'other' ? 'selected' : '' }}>أخرى</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">تفاصيل الاستفسار <span style="color: #dc3545;">*</span></label>
                        <textarea id="message" name="message" rows="6" required placeholder="اكتب تفاصيل استفسارك هنا...">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        إرسال الاستفسار
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
