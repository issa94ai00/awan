@extends('admin.layout')

@section('title', 'إنشاء فاتورة جديدة')

@push('styles')
<style>
    .invoice-create-layout {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    .invoice-left-panel {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .invoice-right-panel {
        position: sticky;
        top: 90px;
    }

    /* Customer & Product Search Section */
    .search-section {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        padding: 24px;
    }

    .search-section .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .search-section .section-title i {
        color: #005a9c;
        font-size: 16px;
    }

    .product-search-wrapper {
        position: relative;
    }

    .product-search-input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Cairo', sans-serif;
        transition: all 0.3s ease;
        background: #f8fafc;
    }

    .product-search-input:focus {
        outline: none;
        border-color: #005a9c;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0, 90, 156, 0.1);
    }

    .product-search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
        pointer-events: none;
    }

    .search-results-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        max-height: 320px;
        overflow-y: auto;
        z-index: 100;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .search-results-dropdown.active {
        display: block;
    }

    .search-result-item {
        padding: 12px 16px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.2s ease;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background: #f0f7ff;
    }

    .search-result-info {
        flex: 1;
    }

    .search-result-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
        margin-bottom: 2px;
    }

    .search-result-meta {
        font-size: 12px;
        color: #64748b;
        display: flex;
        gap: 12px;
    }

    .search-result-price {
        font-weight: 700;
        color: #005a9c;
        font-size: 15px;
        white-space: nowrap;
    }

    .search-no-results {
        padding: 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 14px;
    }

    /* Items Table */
    .items-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        overflow: hidden;
    }

    .items-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .items-card-header .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .items-card-header .section-title i {
        color: #005a9c;
    }

    .items-count-badge {
        background: #e0e7ff;
        color: #005a9c;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .items-table thead th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: right;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #f1f5f9;
    }

    .items-table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .items-table tbody tr:last-child td {
        border-bottom: none;
    }

    .items-table tbody tr:hover {
        background: #fafbfc;
    }

    .item-product-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
    }

    .item-sku {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 2px;
    }

    .qty-control {
        display: flex;
        align-items: center;
        gap: 0;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
        width: fit-content;
    }

    .qty-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border: none;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        color: #475569;
        transition: all 0.2s ease;
    }

    .qty-btn:hover {
        background: #005a9c;
        color: #fff;
    }

    .qty-btn:active {
        transform: scale(0.95);
    }

    .qty-input {
        width: 50px;
        height: 36px;
        border: none;
        text-align: center;
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
        font-family: 'Cairo', sans-serif;
        background: #fff;
    }

    .qty-input:focus {
        outline: none;
        background: #f0f7ff;
    }

    .item-unit-price {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .item-unit-price input {
        width: 90px;
        padding: 8px 10px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        font-family: 'Cairo', sans-serif;
        text-align: right;
        transition: border-color 0.2s ease;
    }

    .item-unit-price input:focus {
        outline: none;
        border-color: #005a9c;
    }

    .item-unit-price .currency-symbol {
        color: #94a3b8;
        font-size: 13px;
        font-weight: 600;
    }

    .item-total {
        font-weight: 700;
        color: #005a9c;
        font-size: 15px;
        white-space: nowrap;
    }

    .item-remove-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #dc2626;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .item-remove-btn:hover {
        background: #dc2626;
        color: #fff;
        border-color: #dc2626;
    }

    .empty-items {
        padding: 48px 24px;
        text-align: center;
        color: #94a3b8;
    }

    .empty-items i {
        font-size: 48px;
        margin-bottom: 12px;
        color: #cbd5e1;
    }

    .empty-items p {
        font-size: 15px;
        margin: 0;
    }

    .empty-items .hint {
        font-size: 13px;
        margin-top: 6px;
        color: #cbd5e1;
    }

    /* Summary Card */
    .summary-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        overflow: hidden;
    }

    .summary-card-header {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .summary-card-header .section-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .summary-card-header .section-title i {
        color: #005a9c;
    }

    .summary-body {
        padding: 20px 24px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
    }

    .summary-row .label {
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
    }

    .summary-row .value {
        font-weight: 700;
        color: #1e293b;
        font-size: 14px;
    }

    .summary-divider {
        border: none;
        border-top: 2px dashed #f1f5f9;
        margin: 8px 0;
    }

    .summary-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0 8px;
    }

    .summary-total-row .label {
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
    }

    .summary-total-row .value {
        font-size: 24px;
        font-weight: 800;
        color: #005a9c;
    }

    .summary-inputs {
        padding: 16px 0;
        border-top: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .summary-input-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .summary-input-row label {
        color: #475569;
        font-size: 13px;
        font-weight: 600;
        min-width: 60px;
    }

    .summary-input-row input,
    .summary-input-row select {
        flex: 1;
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Cairo', sans-serif;
        transition: border-color 0.2s ease;
    }

    .summary-input-row input:focus,
    .summary-input-row select:focus {
        outline: none;
        border-color: #005a9c;
    }

    /* Notes */
    .notes-section {
        padding: 16px 0 0;
        border-top: 1px solid #f1f5f9;
    }

    .notes-section label {
        font-size: 13px;
        font-weight: 600;
        color: #475569;
        display: block;
        margin-bottom: 8px;
    }

    .notes-section textarea {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Cairo', sans-serif;
        resize: vertical;
        min-height: 80px;
        transition: border-color 0.2s ease;
    }

    .notes-section textarea:focus {
        outline: none;
        border-color: #005a9c;
    }

    /* Submit Button */
    .submit-section {
        padding: 20px 24px;
        border-top: 1px solid #f1f5f9;
    }

    .btn-create-invoice {
        width: 100%;
        padding: 14px 24px;
        background: linear-gradient(135deg, #005a9c 0%, #003d6b 100%);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 700;
        font-family: 'Cairo', sans-serif;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 14px rgba(0, 90, 156, 0.3);
    }

    .btn-create-invoice:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 90, 156, 0.4);
    }

    .btn-create-invoice:active {
        transform: translateY(0);
    }

    .btn-create-invoice:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .invoice-create-layout {
            grid-template-columns: 1fr;
        }

        .invoice-right-panel {
            position: static;
        }
    }

    @media (max-width: 768px) {
        .items-card-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }

        .items-table thead {
            display: none;
        }

        .items-table tbody tr {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
        }

        .items-table tbody td {
            padding: 0;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .items-table tbody td::before {
            content: attr(data-label);
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        .item-unit-price input {
            width: 70px;
        }

        .summary-row,
        .summary-total-row {
            flex-direction: column;
            gap: 4px;
            text-align: center;
        }

        .summary-input-row {
            flex-direction: column;
            gap: 6px;
        }

        .summary-input-row label {
            min-width: auto;
        }
    }

    /* Loading Spinner */
    .search-loading {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        display: none;
    }

    .search-loading.active {
        display: block;
    }

    .spinner-small {
        width: 18px;
        height: 18px;
        border: 2px solid #e2e8f0;
        border-top-color: #005a9c;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Validation Errors */
    .validation-errors {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .validation-errors ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .validation-errors li {
        color: #dc2626;
        font-size: 13px;
        padding: 4px 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .validation-errors li::before {
        content: "\f06a";
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        font-size: 12px;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> إنشاء فاتورة جديدة</h1>
    <a href="{{ route('admin.sales.invoices') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-right"></i> عودة للفواتير
    </a>
</div>

@if ($errors->any())
    <div class="validation-errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.invoices.store') }}" method="POST" id="invoiceForm">
    @csrf

    <div class="invoice-create-layout">
        <!-- Left Panel: Customer Search + Items -->
        <div class="invoice-left-panel">
            <!-- Product Search -->
            <div class="search-section">
                <div class="section-title">
                    <i class="fas fa-search"></i>
                    البحث عن منتج
                </div>
                <div class="product-search-wrapper">
                    <i class="fas fa-search product-search-icon"></i>
                    <div class="search-loading" id="searchLoading">
                        <div class="spinner-small"></div>
                    </div>
                    <input
                        type="text"
                        class="product-search-input"
                        id="productSearch"
                        placeholder="اكتب اسم المنتج، الكود، أو الباركود..."
                        autocomplete="off"
                    >
                    <div class="search-results-dropdown" id="searchResults"></div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-card">
                <div class="items-card-header">
                    <div class="section-title">
                        <i class="fas fa-list"></i>
                        أصناف الفاتورة
                    </div>
                    <span class="items-count-badge" id="itemsCount">0 أصناف</span>
                </div>

                <div id="itemsContainer">
                    <div class="empty-items" id="emptyItems">
                        <i class="fas fa-shopping-cart"></i>
                        <p>لم تتم إضافة أي أصناف بعد</p>
                        <p class="hint">استخدم البحث أعلاه لإضافة منتجات للفاتورة</p>
                    </div>
                </div>

                <table class="items-table" id="itemsTable" style="display: none;">
                    <thead>
                        <tr>
                            <th style="width: 35%">المنتج</th>
                            <th style="width: 15%">الكمية</th>
                            <th style="width: 20%">السعر</th>
                            <th style="width: 20%">الإجمالي</th>
                            <th style="width: 10%"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>
        </div>

        <!-- Right Panel: Summary -->
        <div class="invoice-right-panel">
            <!-- Customer Selection -->
            <div class="search-section" style="margin-bottom: 24px;">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    بيانات العميل
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="customer_id" class="form-control" id="customerSelect">
                        <option value="">اختر العميل (اختياري)</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} - {{ $customer->phone ?? $customer->email }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Summary -->
            <div class="summary-card">
                <div class="summary-card-header">
                    <div class="section-title">
                        <i class="fas fa-calculator"></i>
                        ملخص الفاتورة
                    </div>
                </div>

                <div class="summary-body">
                    <div class="summary-row">
                        <span class="label">الإجمالي الفرعي</span>
                        <span class="value" id="subtotalDisplay">0.00 ل.س</span>
                    </div>

                    <div class="summary-inputs">
                        <div class="summary-input-row">
                            <label for="discountInput">الخصم</label>
                            <input type="number" name="discount" id="discountInput" value="0" min="0" step="0.01" placeholder="0">
                        </div>
                        <div class="summary-input-row">
                            <label for="taxInput">الضريبة</label>
                            <input type="number" name="tax" id="taxInput" value="0" min="0" step="0.01" placeholder="0">
                        </div>
                        <div class="summary-input-row">
                            <label for="paymentMethod">الدفع</label>
                            <select name="payment_method" id="paymentMethod">
                                <option value="cash">نقدي</option>
                                <option value="card">بطاقة</option>
                                <option value="transfer">تحويل</option>
                            </select>
                        </div>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-row">
                        <span class="label">الخصم</span>
                        <span class="value" id="discountDisplay" style="color: #dc2626;">-0.00 ل.س</span>
                    </div>
                    <div class="summary-row">
                        <span class="label">الضريبة</span>
                        <span class="value" id="taxDisplay" style="color: #16a34a;">+0.00 ل.س</span>
                    </div>

                    <hr class="summary-divider">

                    <div class="summary-total-row">
                        <span class="label">الإجمالي</span>
                        <span class="value" id="totalDisplay">0.00 ل.س</span>
                    </div>
                </div>

                <div class="submit-section">
                    <button type="submit" class="btn-create-invoice" id="submitBtn" disabled>
                        <i class="fas fa-check-circle"></i>
                        إنشاء الفاتورة
                    </button>
                </div>
            </div>

            <!-- Notes -->
            <div class="search-section" style="margin-top: 24px;">
                <div class="notes-section">
                    <label for="notesInput">ملاحظات</label>
                    <textarea name="notes" id="notesInput" rows="3" placeholder="أضف ملاحظات للفاتورة..."></textarea>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<script>
(function() {
    const items = [];
    const productSearch = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const searchLoading = document.getElementById('searchLoading');
    const itemsBody = document.getElementById('itemsBody');
    const itemsTable = document.getElementById('itemsTable');
    const emptyItems = document.getElementById('emptyItems');
    const itemsCount = document.getElementById('itemsCount');
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const discountDisplay = document.getElementById('discountDisplay');
    const taxDisplay = document.getElementById('taxDisplay');
    const totalDisplay = document.getElementById('totalDisplay');
    const discountInput = document.getElementById('discountInput');
    const taxInput = document.getElementById('taxInput');
    const submitBtn = document.getElementById('submitBtn');

    let searchTimeout = null;

    // Product search
    productSearch.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.classList.remove('active');
            searchResults.innerHTML = '';
            return;
        }

        searchLoading.classList.add('active');

        searchTimeout = setTimeout(() => {
            fetch(`/admin/invoices/search-products?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => {
                    searchLoading.classList.remove('active');
                    renderSearchResults(products);
                })
                .catch(() => {
                    searchLoading.classList.remove('active');
                    searchResults.classList.remove('active');
                });
        }, 300);
    });

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!productSearch.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.remove('active');
        }
    });

    // Focus on search input shows results if any
    productSearch.addEventListener('focus', function() {
        if (searchResults.children.length > 0) {
            searchResults.classList.add('active');
        }
    });

    function renderSearchResults(products) {
        if (products.length === 0) {
            searchResults.innerHTML = '<div class="search-no-results"><i class="fas fa-search"></i> لا توجد نتائج</div>';
            searchResults.classList.add('active');
            return;
        }

        searchResults.innerHTML = products.map(p => `
            <div class="search-result-item" data-product='${JSON.stringify(p)}'>
                <div class="search-result-info">
                    <div class="search-result-name">${p.name_ar || p.name_en}</div>
                    <div class="search-result-meta">
                        <span><i class="fas fa-barcode"></i> ${p.sku || '-'}</span>
                        <span><i class="fas fa-box"></i> ${p.stock_quantity} ${p.unit || 'unit'}</span>
                    </div>
                </div>
                <div class="search-result-price">${Number(p.price).toFixed(2)} ل.س</div>
            </div>
        `).join('');

        searchResults.classList.add('active');

        // Add click events
        searchResults.querySelectorAll('.search-result-item').forEach(item => {
            item.addEventListener('click', function() {
                const product = JSON.parse(this.dataset.product);
                addProductToInvoice(product);
                productSearch.value = '';
                searchResults.classList.remove('active');
                searchResults.innerHTML = '';
            });
        });
    }

    function addProductToInvoice(product) {
        // Check if product already exists
        const existingIndex = items.findIndex(i => i.product_id === product.id);
        if (existingIndex !== -1) {
            items[existingIndex].quantity += 1;
            renderItems();
            return;
        }

        items.push({
            product_id: product.id,
            name: product.name_ar || product.name_en,
            sku: product.sku || '',
            price: parseFloat(product.price) || 0,
            quantity: 1,
            unit: product.unit || '',
            stock: product.stock_quantity || 0,
            tax_rate: parseFloat(product.tax_rate) || 0,
            taxable: product.taxable || false
        });

        renderItems();
    }

    function renderItems() {
        itemsBody.innerHTML = '';

        if (items.length === 0) {
            itemsTable.style.display = 'none';
            emptyItems.style.display = 'block';
            submitBtn.disabled = true;
        } else {
            itemsTable.style.display = 'table';
            emptyItems.style.display = 'none';
            submitBtn.disabled = false;
        }

        itemsCount.textContent = `${items.length} أصناف`;

        items.forEach((item, index) => {
            const total = item.price * item.quantity;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="المنتج">
                    <div class="item-product-name">${item.name}</div>
                    <div class="item-sku">${item.sku ? 'SKU: ' + item.sku : ''}</div>
                </td>
                <td data-label="الكمية">
                    <div class="qty-control">
                        <button type="button" class="qty-btn qty-minus" data-index="${index}">-</button>
                        <input type="number" class="qty-input" value="${item.quantity}" min="1" data-index="${index}">
                        <button type="button" class="qty-btn qty-plus" data-index="${index}">+</button>
                    </div>
                </td>
                <td data-label="السعر">
                    <div class="item-unit-price">
                        <input type="number" value="${item.price.toFixed(2)}" min="0" step="0.01" data-index="${index}" class="price-input">
                        <span class="currency-symbol">ل.س</span>
                    </div>
                </td>
                <td data-label="الإجمالي">
                    <div class="item-total">${total.toFixed(2)} ل.س</div>
                </td>
                <td>
                    <button type="button" class="item-remove-btn" data-index="${index}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            itemsBody.appendChild(tr);
        });

        // Add event listeners
        document.querySelectorAll('.qty-minus').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index);
                if (items[idx].quantity > 1) {
                    items[idx].quantity -= 1;
                    renderItems();
                }
            });
        });

        document.querySelectorAll('.qty-plus').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index);
                items[idx].quantity += 1;
                renderItems();
            });
        });

        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.dataset.index);
                const val = parseInt(this.value);
                if (val >= 1) {
                    items[idx].quantity = val;
                    renderItems();
                }
            });
        });

        document.querySelectorAll('.price-input').forEach(input => {
            input.addEventListener('change', function() {
                const idx = parseInt(this.dataset.index);
                const val = parseFloat(this.value);
                if (val >= 0) {
                    items[idx].price = val;
                    renderItems();
                }
            });
        });

        document.querySelectorAll('.item-remove-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = parseInt(this.dataset.index);
                items.splice(idx, 1);
                renderItems();
            });
        });

        updateSummary();
    }

    function updateSummary() {
        let subtotal = 0;
        items.forEach(item => {
            subtotal += item.price * item.quantity;
        });

        const discount = parseFloat(discountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;
        const total = Math.max(0, subtotal - discount + tax);

        subtotalDisplay.textContent = subtotal.toFixed(2) + ' ل.س';
        discountDisplay.textContent = '-' + discount.toFixed(2) + ' ل.س';
        taxDisplay.textContent = '+' + tax.toFixed(2) + ' ل.س';
        totalDisplay.textContent = total.toFixed(2) + ' ل.س';
    }

    discountInput.addEventListener('input', updateSummary);
    taxInput.addEventListener('input', updateSummary);

    // Before submit, populate hidden inputs for items
    document.getElementById('invoiceForm').addEventListener('submit', function(e) {
        if (items.length === 0) {
            e.preventDefault();
            alert('يرجى إضافة منتج واحد على الأقل');
            return;
        }

        // Remove old hidden inputs
        this.querySelectorAll('input[name^="items"]').forEach(el => el.remove());

        items.forEach((item, index) => {
            const fields = {
                [`items[${index}][product_id]`]: item.product_id,
                [`items[${index}][quantity]`]: item.quantity,
                [`items[${index}][unit_price]`]: item.price
            };

            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                this.appendChild(input);
            }
        });
    });

    // Keyboard navigation for search results
    productSearch.addEventListener('keydown', function(e) {
        const results = searchResults.querySelectorAll('.search-result-item');
        const active = searchResults.querySelector('.search-result-item.active');
        let currentIndex = -1;

        if (active) {
            currentIndex = Array.from(results).indexOf(active);
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (currentIndex < results.length - 1) {
                if (active) active.classList.remove('active');
                results[currentIndex + 1].classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (currentIndex > 0) {
                if (active) active.classList.remove('active');
                results[currentIndex - 1].classList.add('active');
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (active) {
                active.click();
            }
        }
    });
})();
</script>
@endsection
