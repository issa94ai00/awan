// Admin Panel JavaScript - Awan Altakaddom

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 992) {
            if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Nav Group Toggle
    document.querySelectorAll('.nav-group-header').forEach(header => {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            const navGroup = this.closest('.nav-group');

            document.querySelectorAll('.nav-group.open').forEach(group => {
                if (group !== navGroup) {
                    group.classList.remove('open');
                }
            });

            navGroup.classList.toggle('open');
        });
    });

    // User Dropdown Toggle
    const userDropdown = document.querySelector('.user-dropdown');
    const userBtn = userDropdown?.querySelector('.user-btn');
    const dropdownMenu = userDropdown?.querySelector('.dropdown-menu');

    if (userBtn && dropdownMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            dropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                dropdownMenu.classList.remove('show');
            }
        });
    }

    // Auto-hide success/error alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'all 0.3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Confirm delete forms
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const msg = this.getAttribute('onsubmit')?.replace('return confirm(\'', '').replace('\');', '');
            if (msg && !confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Loading state on form submit (exclude logout)
    document.querySelectorAll('form:not(.logout-form)').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.dataset.originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
        });
    });

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const content = document.getElementById(tabId);
            if (content) content.classList.add('active');
        });
    });

    // Remove invalid class on input
    document.querySelectorAll('.form-control').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            const feedback = this.parentElement.querySelector('.invalid-feedback');
            if (feedback) feedback.remove();
        });
    });

    // Tooltip conversion: data-tooltip from title
    document.querySelectorAll('[title]').forEach(el => {
        const title = el.getAttribute('title');
        if (title) {
            el.setAttribute('data-tooltip', title);
            el.removeAttribute('title');
        }
    });
});

// Toast notification helper
window.showToast = function(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    if (!container) {
        const newContainer = document.createElement('div');
        newContainer.className = 'toast-container';
        document.body.appendChild(newContainer);
    }

    const toast = document.createElement('div');
    toast.className = 'toast ' + type;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i> ' + message;
    (document.querySelector('.toast-container') || document.body.appendChild(document.createElement('div'))).appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'all 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};

// Confirm delete helper
window.confirmDelete = function(message) {
    return confirm(message || 'هل أنت متأكد من الحذف؟');
};

// AJAX helper
window.ajaxRequest = function(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            'Accept': 'application/json',
        }
    };
    return fetch(url, { ...defaultOptions, ...options })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        });
};
