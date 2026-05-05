document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    const searchContainer = searchInput ? searchInput.closest('.search-container') : null;
    const searchIcon = searchContainer ? searchContainer.querySelector('.search-icon') : null;

    if (!searchInput || !searchResults) return;

    const MIN_QUERY = 2;
    const DEBOUNCE_DELAY = 300;
    let debounceTimer = null;
    let isLoading = false;

    function setIconTypingState(isTyping) {
        if (!searchIcon) return;
        searchIcon.classList.toggle('is-typing', Boolean(isTyping));
    }

    function setLoadingState(loading) {
        isLoading = loading;
        if (loading) {
            searchResults.innerHTML = '<div class="search-result-empty"><i class="fas fa-spinner fa-spin"></i> جاري البحث...</div>';
            searchResults.classList.add('active');
        }
    }

    function hide() {
        searchResults.classList.remove('active');
        setIconTypingState(false);
    }

    function show(html) {
        searchResults.innerHTML = html;
        searchResults.classList.add('active');
    }

    function escapeHTML(str) {
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function highlightMatch(text, query) {
        if (!query) return escapeHTML(text);
        const regex = new RegExp(`(${escapeHTML(query)})`, 'gi');
        return escapeHTML(text).replace(regex, '<mark>$1</mark>');
    }

    function renderResults(data, query) {
        const products = data.products || [];
        const categories = data.categories || [];
        const totalResults = data.total_results || 0;

        if (totalResults === 0) {
            return '<div class="search-result-empty">لا توجد نتائج</div>';
        }

        let html = '';

        if (categories.length > 0) {
            html += '<div style="padding: 0.5rem 1rem; background: #f8f9fa; font-weight: 600; font-size: 0.85rem; color: #666;">الفئات</div>';
            html += categories.map((cat) => {
                const highlighted = highlightMatch(cat.name_ar, query);
                return (
                    `<a href="${escapeHTML(cat.url)}" class="search-result-item">` +
                    `<span class="search-result-icon"><i class="fas fa-folder"></i></span>` +
                    `<span class="search-result-text">${highlighted} <small style="color: #999;">(${cat.product_count} منتج)</small></span>` +
                    `</a>`
                );
            }).join('');
        }

        if (products.length > 0) {
            html += '<div style="padding: 0.5rem 1rem; background: #f8f9fa; font-weight: 600; font-size: 0.85rem; color: #666; border-top: 1px solid #eee;">المنتجات</div>';
            html += products.map((prod) => {
                const highlighted = highlightMatch(prod.name_ar, query);
                const priceText = prod.price ? `<small style="color: var(--accent-blue);">$${prod.price}</small>` : '';
                return (
                    `<a href="${escapeHTML(prod.url)}" class="search-result-item">` +
                    `<span class="search-result-icon"><i class="fas fa-box"></i></span>` +
                    `<span class="search-result-text">${highlighted} ${priceText}</span>` +
                    `</a>`
                );
            }).join('');
        }

        if (totalResults > 0) {
            html += `<a href="/categories" class="search-result-item" style="background: var(--accent-blue); color: white; text-align: center; justify-content: center;">` +
                    `<span class="search-result-text">عرض جميع النتائج (${totalResults})</span>` +
                    `</a>`;
        }

        return html;
    }

    function normalize(s) {
        return String(s || '').trim().toLowerCase();
    }

    async function performSearch(query) {
        if (query.length < MIN_QUERY) {
            hide();
            return;
        }

        setLoadingState(true);
        setIconTypingState(true);

        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
            if (!response.ok) throw new Error('Search failed');

            const data = await response.json();
            show(renderResults(data, query));
        } catch (error) {
            console.error('Search error:', error);
            show('<div class="search-result-empty">حدث خطأ في البحث</div>');
        } finally {
            setLoadingState(false);
        }
    }

    searchInput.addEventListener('input', function (e) {
        const queryRaw = String(e.target.value || '');
        const query = normalize(queryRaw);

        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }

        setIconTypingState(query.length > 0);

        if (query.length < MIN_QUERY) {
            hide();
            return;
        }

        debounceTimer = setTimeout(() => {
            performSearch(query);
        }, DEBOUNCE_DELAY);
    });

    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hide();
            searchInput.blur();
        }
    });

    searchResults.addEventListener('click', function (e) {
        const item = e.target.closest('.search-result-item');
        if (!item) return;

        if (item.tagName === 'A') {
            hide();
            return;
        }

        const value = item.getAttribute('data-value') || '';
        searchInput.value = value;
        hide();
        searchInput.focus();
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            hide();
        }
    });
});
