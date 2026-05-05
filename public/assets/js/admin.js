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

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Confirm delete actions
    const deleteForms = document.querySelectorAll('form[onsubmit]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const confirmMessage = this.getAttribute('onsubmit');
            if (confirmMessage && !eval(confirmMessage)) {
                e.preventDefault();
            }
        });
    });

    // Table row hover effect
    const tableRows = document.querySelectorAll('.table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(0, 90, 156, 0.03)';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });

    // Form validation feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            console.log('Form submit triggered');
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');

                    // Add error message if not exists
                    let feedback = field.parentElement.querySelector('.invalid-feedback');
                    if (!feedback) {
                        feedback = document.createElement('span');
                        feedback.className = 'invalid-feedback';
                        feedback.textContent = 'هذا الحقل مطلوب';
                        field.parentElement.appendChild(feedback);
                    }
                } else {
                    field.classList.remove('is-invalid');
                    const feedback = field.parentElement.querySelector('.invalid-feedback');
                    if (feedback) feedback.remove();
                }
            });

            if (!isValid) {
                e.preventDefault();
                console.log('Form validation failed');
            } else {
                console.log('Form validation passed, submitting...');
            }
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

    // Image preview for file inputs
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                const previewContainer = this.parentElement.querySelector('.image-preview');

                reader.onload = function(e) {
                    if (previewContainer) {
                        const img = previewContainer.querySelector('img');
                        if (img) {
                            img.src = e.target.result;
                        } else {
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.style.maxWidth = '200px';
                            newImg.style.borderRadius = '8px';
                            newImg.style.marginTop = '1rem';
                            previewContainer.appendChild(newImg);
                        }
                        previewContainer.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Tab switching (if tabs exist)
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;

            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            // Add active class to current tab and content
            this.classList.add('active');
            const content = document.getElementById(tabId);
            if (content) {
                content.classList.add('active');
            }
        });
    });

    // Search input focus effect
    document.querySelectorAll('.header-search input, .search-form input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.boxShadow = '0 0 0 3px rgba(0, 90, 156, 0.1)';
        });
        input.addEventListener('blur', function() {
            this.parentElement.style.boxShadow = '';
        });
    });

    // Loading state for buttons (exclude logout form)
    document.querySelectorAll('form:not(.logout-form)').forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = form.querySelector('button[type="submit"]');
            if (!btn) return;
            
            // Don't disable button, just show loading state
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            btn.dataset.originalText = originalText;
            
            // Reset button after 5 seconds if page hasn't navigated away
            setTimeout(() => {
                if (btn && document.body.contains(btn)) {
                    btn.innerHTML = originalText;
                }
            }, 5000);
        });
    });

    // Tooltip initialization (simple tooltips)
    document.querySelectorAll('[title]').forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-title', title);
                this.removeAttribute('title');

                const tooltip = document.createElement('div');
                tooltip.className = 'simple-tooltip';
                tooltip.textContent = title;
                tooltip.style.cssText = `
                    position: fixed;
                    background: #333;
                    color: white;
                    padding: 5px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 9999;
                    pointer-events: none;
                    white-space: nowrap;
                `;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
                tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';

                this._tooltip = tooltip;
            }
        });

        el.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
            if (this.getAttribute('data-title')) {
                this.setAttribute('title', this.getAttribute('data-title'));
                this.removeAttribute('data-title');
            }
        });
    });
});

// Helper functions
window.confirmDelete = function(message) {
    return confirm(message || 'هل أنت متأكد من الحذف؟');
};

window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
    }
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
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
};
