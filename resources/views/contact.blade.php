@extends('layout')

@section('title', 'إتصل بنا')

@section('content')
<section class="page-header" style="padding-top: 120px; background: linear-gradient(135deg, var(--primary-dark), var(--accent-blue));">
    <div class="container">
        <h1>إتصل بنا</h1>
        <div class="breadcrumb">
            <a href="{{ route('home') }}">الرئيسية</a>
            <span>/</span>
            <span>إتصل بنا</span>
        </div>
    </div>
</section>

<section class="contact-section fade-up" id="contact">
    <div class="container">
        <div class="section-header">
            <h2>نحن هنا لمساعدتك</h2>
            <p>تواصل معنا للاستفسارات، الطلبات، أو أي معلومات تحتاجها</p>
        </div>

        <div class="contact-wrapper">
            <div class="contact-info">
                <h3>معلومات التواصل</h3>
                
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i>
                    <div>
                        <h4>الهاتف</h4>
                        <p dir="ltr"><?php echo get_setting('contact_phone') ?? '+963 900 000 000'; ?></p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <h4>البريد الإلكتروني</h4>
                        <p><?php echo get_setting('contact_email') ?? 'info@awan-altakaddom.com'; ?></p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <h4>الموقع</h4>
                        <p>سورية - دمشق</p>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <h4>ساعات العمل</h4>
                        <p>السبت - الخميس: 9:00 ص - 6:00 م</p>
                    </div>
                </div>

                <div class="social-links">
                    <h4>تابعنا على</h4>
                    <div class="social-icons">
                        <a href="<?php echo get_setting('contact_facebook') ?? '#'; ?>" class="social-icon facebook" target="_blank">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://wa.me/<?php echo get_setting('contact_whatsapp') ?? '963900000000'; ?>" class="social-icon whatsapp" target="_blank">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="<?php echo get_setting('contact_instagram') ?? '#'; ?>" class="social-icon instagram" target="_blank">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="contact-form-wrapper">
                <h3>أرسل لنا رسالة</h3>
                <form class="contact-form" action="{{ route('contact.store') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label for="name">الاسم الكامل</label>
                        <input type="text" id="name" name="name" required placeholder="أدخل اسمك الكامل">
                    </div>

                    <div class="form-group">
                        <label for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" required placeholder="example@email.com">
                    </div>

                    <div class="form-group">
                        <label for="phone">رقم الهاتف</label>
                        <input type="tel" id="phone" name="phone" placeholder="+963 ...">
                    </div>

                    <div class="form-group">
                        <label for="subject">الموضوع</label>
                        <select id="subject" name="subject" required>
                            <option value="">اختر الموضوع</option>
                            <option value="inquiry">استفسار عام</option>
                            <option value="order">طلب منتجات</option>
                            <option value="support">دعم فني</option>
                            <option value="partnership">شراكة تجارية</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">الرسالة</label>
                        <textarea id="message" name="message" rows="5" required placeholder="اكتب رسالتك هنا..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        إرسال الرسالة
                    </button>
                </form>
            </div>
        </div>

        <div class="quick-contact">
            <h3>تواصل مباشر</h3>
            <div class="quick-buttons">
                <a href="https://wa.me/<?php echo get_setting('contact_whatsapp') ?? '963900000000'; ?>?text=مرحباً، أنا مهتم بمعرفة المزيد عن منتجاتكم" class="quick-btn whatsapp" target="_blank">
                    <i class="fab fa-whatsapp"></i>
                    <span>واتساب</span>
                </a>
                <a href="tel:<?php echo get_setting('contact_phone') ?? '+963900000000'; ?>" class="quick-btn phone">
                    <i class="fas fa-phone"></i>
                    <span>اتصل الآن</span>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
