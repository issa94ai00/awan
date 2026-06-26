import { nextTick } from 'vue';

/**
 * Utility: triggerFadeUp
 * 
 * Re-initializes the IntersectionObserver for elements with the '.fade-up' class.
 * Since Vue renders components dynamically, static scripts in main.js run too early.
 * This function should be called after route transitions and async data loads.
 */
export function triggerFadeUp() {
    nextTick(() => {
        if (!('IntersectionObserver' in window)) {
            // Fallback: make all visible if browser doesn't support IntersectionObserver
            const fadeElements = document.querySelectorAll('.fade-up');
            fadeElements.forEach(el => el.classList.add('visible'));
            return;
        }

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        const fadeElements = document.querySelectorAll('.fade-up');
        fadeElements.forEach(el => observer.observe(el));
    });
}
