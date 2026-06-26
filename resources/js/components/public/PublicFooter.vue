<template>
    <footer class="footer" id="site-contact">
        <div class="footer-container">
            <div class="footer-content">
                <!-- About Section -->
                <div class="footer-section">
                    <div class="footer-logo">
                        <img :src="siteLogo" :alt="siteName" @error="handleLogoError">
                        <span>{{ siteName }}</span>
                    </div>
                    <p class="footer-description">{{ siteDescription }}</p>
                    <div class="social-links">
                        <a :href="facebookUrl" class="social-link" target="_blank">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a :href="whatsappUrl" class="social-link" target="_blank">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a :href="instagramUrl" class="social-link" target="_blank">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-section">
                    <h3>{{ $t('quick_links') }}</h3>
                    <ul class="footer-links">
                        <li><router-link to="/">{{ $t('nav_home') }}</router-link></li>
                        <li><router-link to="/about">{{ $t('nav_about') }}</router-link></li>
                        <li><router-link to="/vision">{{ $t('nav_vision') }}</router-link></li>
                        <li><router-link to="/contact">{{ $t('nav_contact') }}</router-link></li>
                    </ul>
                </div>
                
                <!-- Services -->
                <div class="footer-section">
                    <h3>{{ $t('our_services') }}</h3>
                    <ul class="footer-links">
                        <li><router-link to="/contact">{{ $t('technical_consultation') }}</router-link></li>
                        <li><router-link to="/contact">{{ $t('request_quote') }}</router-link></li>
                        <li><router-link to="/contact">{{ $t('delivery_shipping') }}</router-link></li>
                        <li><router-link to="/contact">{{ $t('technical_support') }}</router-link></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div class="footer-section">
                    <h3>{{ $t('contact_us') }}</h3>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>{{ $p(settings, 'address') || (locale === 'en' ? 'Syria - Damascus' : 'سورية - دمشق') }}</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span dir="ltr">{{ contactPhone }}</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>{{ contactEmail }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="footer-bottom">
                <p>&copy; {{ currentYear }} {{ siteName }} - {{ $t('all_rights_reserved') }}</p>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.data);

const siteName = computed(() => $p(settings.value, 'site_name') || 'أوان التقدم');
const siteLogo = computed(() => {
    const logo = settings.value.site_logo;
    if (logo) {
        // Handle different logo path formats
        if (logo.startsWith('http://') || logo.startsWith('https://')) {
            return logo;
        }
        if (logo.startsWith('assets/')) {
            return `/${logo}`;
        }
        if (logo.startsWith('/')) {
            return logo;
        }
        return `/storage/${logo}`;
    }
    // Fallback to default logo
    return '/assets/images/logo.png';
});
const siteDescription = computed(() => $p(settings.value, 'site_description') || t('site_description_default'));
const facebookUrl = computed(() => settings.value.facebook_url || '#');
const whatsappUrl = computed(() => settings.value.whatsapp_url || 'https://wa.me/963900000000');
const instagramUrl = computed(() => settings.value.instagram_url || '#');
const contactPhone = computed(() => settings.value.contact_phone || '+963 900 000 000');
const contactEmail = computed(() => settings.value.contact_email || 'info@awan-altakaddom.com');

const currentYear = computed(() => new Date().getFullYear());

function handleLogoError(event) {
    // Fallback to default logo if the configured logo fails to load
    event.target.src = '/assets/images/logo.png';
}

// Fetch settings on mount
settingsStore.fetch().catch((err) => console.warn(err));
</script>

<style scoped>
.footer {
    background: linear-gradient(135deg, #1e3a8a 0%, #111827 100%);
    color: white;
    padding: 4rem 0 2rem;
    margin-top: auto;
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

.footer-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 3rem;
    margin-bottom: 3rem;
}

.footer-section h3 {
    font-size: 1.125rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    color: white;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.footer-logo img {
    height: 50px;
    width: auto;
}

.footer-logo span {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.footer-description {
    color: #9ca3af;
    line-height: 1.8;
    margin-bottom: 1.5rem;
}

.social-links {
    display: flex;
    gap: 1rem;
}

.social-link {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    transition: all 0.3s;
}

.social-link:hover {
    background: #3b82f6;
    transform: translateY(-3px);
}

.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-links li {
    margin-bottom: 0.75rem;
}

.footer-links a {
    color: #9ca3af;
    text-decoration: none;
    transition: color 0.3s;
}

.footer-links a:hover {
    color: #3b82f6;
}

.contact-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #9ca3af;
}

.contact-item i {
    color: #3b82f6;
    font-size: 1.125rem;
}

.footer-bottom {
    padding-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.footer-bottom p {
    color: #9ca3af;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}
</style>
