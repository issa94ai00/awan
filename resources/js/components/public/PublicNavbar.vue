<template>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-content">
                <!-- Logo -->
                <router-link to="/" class="navbar-logo">
                    <img :src="siteLogo" :alt="siteName">
                    <span v-if="showSiteName">{{ siteName }}</span>
                </router-link>

                <!-- Desktop Navigation -->
                <div class="navbar-nav desktop-nav">
                    <router-link to="/" class="nav-link">الرئيسية</router-link>
                    <router-link to="/categories" class="nav-link">الفئات</router-link>
                    <router-link to="/featured-products" class="nav-link">المنتجات المميزة</router-link>
                    <router-link to="/vision" class="nav-link">الهوية والرؤية</router-link>
                    <router-link to="/about" class="nav-link">من نحن</router-link>
                    <router-link to="/contact" class="nav-link">اتصل بنا</router-link>
                </div>

                <!-- Search & Actions -->
                <div class="navbar-actions">
                    <div class="search-box desktop-search">
                        <input 
                            type="text" 
                            v-model="searchQuery"
                            @keyup.enter="handleSearch"
                            placeholder="ابحث عن منتج..."
                        >
                        <i class="fas fa-search search-icon" @click="handleSearch"></i>
                    </div>

                    <button 
                        @click="toggleDarkMode"
                        class="action-btn"
                        title="الوضع الداكن"
                    >
                        <i :class="isDarkMode ? 'fas fa-sun' : 'fas fa-moon'"></i>
                    </button>

                    <button 
                        @click="toggleMenu"
                        class="action-btn mobile-menu-btn"
                    >
                        <i :class="isMenuOpen ? 'fas fa-times' : 'fas fa-bars'"></i>
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div v-show="isMenuOpen" class="mobile-menu">
                <div class="mobile-search">
                    <input 
                        type="text" 
                        v-model="searchQuery"
                        @keyup.enter="handleSearch"
                        placeholder="ابحث عن منتج..."
                    >
                    <i class="fas fa-search search-icon" @click="handleSearch"></i>
                </div>
                <div class="mobile-nav">
                    <router-link to="/" class="mobile-nav-link" @click="isMenuOpen = false">الرئيسية</router-link>
                    <router-link to="/categories" class="mobile-nav-link" @click="isMenuOpen = false">الفئات</router-link>
                    <router-link to="/featured-products" class="mobile-nav-link" @click="isMenuOpen = false">المنتجات المميزة</router-link>
                    <router-link to="/vision" class="mobile-nav-link" @click="isMenuOpen = false">الهوية والرؤية</router-link>
                    <router-link to="/about" class="mobile-nav-link" @click="isMenuOpen = false">من نحن</router-link>
                    <router-link to="/contact" class="mobile-nav-link" @click="isMenuOpen = false">اتصل بنا</router-link>
                </div>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const searchQuery = ref('');
const isDarkMode = ref(false);
const isMenuOpen = ref(false);

// Settings (these should come from API in production)
const siteName = ref('أوان التقدم');
const siteLogo = ref('/assets/images/logo.png');
const showSiteName = ref(false);

function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle('dark', isDarkMode.value);
    localStorage.setItem('darkMode', isDarkMode.value);
}

function toggleMenu() {
    isMenuOpen.value = !isMenuOpen.value;
}

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.push({ name: 'search', query: { q: searchQuery.value } });
    }
}

onMounted(() => {
    // Load dark mode preference
    const savedDarkMode = localStorage.getItem('darkMode');
    if (savedDarkMode === 'true') {
        isDarkMode.value = true;
        document.documentElement.classList.add('dark');
    }
});
</script>

<style scoped>
.navbar {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s;
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
}

.navbar-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

.navbar-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 80px;
}

.navbar-logo {
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
}

.navbar-logo img {
    height: 50px;
    width: auto;
}

.navbar-logo span {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e3a8a;
}

.navbar-nav {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.nav-link {
    color: #374151;
    text-decoration: none;
    font-weight: 500;
    font-size: 1rem;
    transition: all 0.3s;
    position: relative;
}

.nav-link:hover,
.nav-link.router-link-active {
    color: #3b82f6;
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: #3b82f6;
    transition: width 0.3s;
}

.nav-link:hover::after,
.nav-link.router-link-active::after {
    width: 100%;
}

.navbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box input {
    padding: 0.75rem 3rem 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    font-size: 0.875rem;
    width: 250px;
    transition: all 0.3s;
    outline: none;
}

.search-box input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-icon {
    position: absolute;
    left: 1rem;
    color: #9ca3af;
    cursor: pointer;
    transition: color 0.3s;
}

.search-icon:hover {
    color: #3b82f6;
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: #f3f4f6;
    color: #374151;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.action-btn:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-2px);
}

.mobile-menu-btn {
    display: none;
}

.desktop-nav,
.desktop-search {
    display: flex;
}

.mobile-menu {
    display: none;
}

.mobile-search {
    display: none;
    position: relative;
    margin-bottom: 1rem;
}

.mobile-search input {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    font-size: 0.875rem;
    outline: none;
}

.mobile-nav {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.mobile-nav-link {
    padding: 1rem;
    color: #374151;
    text-decoration: none;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s;
}

.mobile-nav-link:hover,
.mobile-nav-link.router-link-active {
    background: #f3f4f6;
    color: #3b82f6;
}

@media (max-width: 1024px) {
    .navbar-container {
        padding: 0 1.5rem;
    }

    .desktop-nav {
        gap: 1.5rem;
    }

    .nav-link {
        font-size: 0.875rem;
    }

    .search-box input {
        width: 200px;
    }
}

@media (max-width: 768px) {
    .navbar-content {
        height: 70px;
    }

    .desktop-nav,
    .desktop-search {
        display: none;
    }

    .mobile-menu-btn {
        display: flex;
    }

    .mobile-menu {
        display: block;
        padding: 1.5rem 0;
        border-top: 1px solid #e5e7eb;
    }

    .mobile-search {
        display: block;
    }

    .navbar-logo span {
        display: none;
    }
}
</style>
