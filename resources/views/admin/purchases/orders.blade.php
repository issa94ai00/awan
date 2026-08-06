@extends('admin.layout')

@section('title', 'أوامر الشراء')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-file-signature"></i> أوامر الشراء</h1>
        <p>إدارة ومتابعة طلبات وأوامر الشراء الصادرة للموردين وتتبع حالة استلامها</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة للمشتريات
        </a>
    </div>
</div>

<!-- Stats Counter Row -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="fas fa-file-signature"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\PurchaseOrder::count() }}</h3>
            <p>إجمالي أوامر الشراء</p>
            <small>الطلبات الصادرة بالنظام</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\PurchaseOrder::where('status', 'pending')->count() }}</h3>
            <p>أوامر معلقة</p>
            <small>بانتظار التأكيد من المورد</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format(\App\Models\PurchaseOrder::sum('total'), 2) }}</h3>
            <p>قيمة المشتريات</p>
            <small>المبالغ الإجمالية للأوامر</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.purchases.orders') }}" method="GET" class="filter-form-wrapper" style="width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="search-form" style="margin: 0; flex: 1; min-width: 280px;">
                <div class="input-group" style="position: relative; display: flex; align-items: center;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث برقم أمر الشراء أو اسم المورد..." class="form-control" style="padding-right: 2.5rem; width: 100%;">
                    <button type="submit" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <select name="status" class="form-control" style="min-width: 160px; padding: 0.5rem 1rem;" onchange="this.form.submit()">
                    <option value="">جميع الحالات</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلق</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>مؤكد</option>
                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>قيد المعالجة</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتمل</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                </select>
                
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.purchases.orders') }}" class="btn btn-secondary" style="padding: 0.625rem 1rem; border-radius: 8px;">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        @if($orders->count())
            <div class="table-responsive">
                <table class="table table-striped" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 120px;">رقم الطلب</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700;">المورد</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">المبلغ الإجمالي</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">تاريخ الإنشاء</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">حالة الطلب</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 180px; text-align: center;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $statusText = match(strtolower($order->status)) {
                                    'pending' => 'معلق',
                                    'confirmed' => 'مؤكد',
                                    'processing' => 'قيد المعالجة',
                                    'completed' => 'مكتمل',
                                    'cancelled' => 'ملغي',
                                    default => $order->status
                                };
                                $badgeClass = match(strtolower($order->status)) {
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'processing' => 'secondary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <tr>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <strong style="color: var(--accent-blue);">{{ $order->order_number }}</strong>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-truck" style="font-size: 1.1rem; color: var(--text-light);"></i>
                                        <strong>{{ $order->supplier?->name ?? 'غير محدد' }}</strong>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text-dark);">
                                    ${{ number_format($order->total, 2) }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-muted);">
                                    {{ $order->created_at->format('Y-m-d') }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <span class="badge badge-{{ $badgeClass }}" style="font-size: 0.8rem; padding: 0.375rem 0.75rem;">
                                        <i class="fas @if($order->status === 'completed') fa-check-circle @elseif($order->status === 'pending') fa-clock @elseif($order->status === 'cancelled') fa-times-circle @else fa-sync-alt @endif"></i>
                                        {{ $statusText }}
                                    </span>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                    @if(in_array(strtolower($order->status), ['confirmed', 'processing']))
                                        <a href="{{ route('admin.purchase-receipts.create', ['purchase_order_id' => $order->id]) }}" class="btn btn-sm btn-success" style="padding: 0.4rem 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8rem;">
                                            <i class="fas fa-arrow-alt-circle-down"></i> تلقي البضاعة
                                        </a>
                                    @else
                                        <span style="color: var(--text-light); font-size: 0.85rem; font-style: italic;">لا توجد إجراءات متاحة</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding: 4rem 2rem; text-align: center;">
                <i class="fas fa-file-signature" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1.25rem; opacity: 0.5;"></i>
                <p style="font-weight: 500; font-size: 1.1rem; color: var(--text-muted);">لا توجد أوامر شراء مطابقة لشروط البحث.</p>
            </div>
        @endif
    </div>
</div>

@if(method_exists($orders, 'links') && $orders->hasPages())
<nav class="pagination-tailwind" aria-label="Pagination" style="margin-top: 1.5rem;">
    <!-- Mobile view -->
    <div class="mobile-pagination">
        @if($orders->onFirstPage())
        <span class="btn-prev disabled">
            <i class="fas fa-chevron-right"></i>
            السابق
        </span>
        @else
        <a href="{{ $orders->previousPageUrl() }}" class="btn-prev">
            <i class="fas fa-chevron-right"></i>
            السابق
        </a>
        @endif

        @if($orders->hasMorePages())
        <a href="{{ $orders->nextPageUrl() }}" class="btn-next">
            التالي
            <i class="fas fa-chevron-left"></i>
        </a>
        @else
        <span class="btn-next disabled">
            التالي
            <i class="fas fa-chevron-left"></i>
        </span>
        @endif
    </div>

    <!-- Desktop view -->
    <div class="desktop-pagination">
        <p class="pagination-info">
            عرض <span>{{ $orders->firstItem() }}</span> إلى <span>{{ $orders->lastItem() }}</span> من <span>{{ $orders->total() }}</span> أمر شراء
        </p>

        <div class="pagination-buttons">
            <!-- Previous -->
            @if($orders->onFirstPage())
            <span class="page-btn prev disabled">
                <i class="fas fa-chevron-right"></i>
            </span>
            @else
            <a href="{{ $orders->previousPageUrl() }}" class="page-btn prev">
                <i class="fas fa-chevron-right"></i>
            </a>
            @endif

            <!-- Page Numbers -->
            @foreach($orders->getUrlRange(max(1, $orders->currentPage() - 2), min($orders->lastPage(), $orders->currentPage() + 2)) as $page => $url)
                @if($page == $orders->currentPage())
                <span class="page-btn active">{{ $page }}</span>
                @else
                <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                @endif
            @endforeach

            <!-- Next -->
            @if($orders->hasMorePages())
            <a href="{{ $orders->nextPageUrl() }}" class="page-btn next">
                <i class="fas fa-chevron-left"></i>
            </a>
            @else
            <span class="page-btn next disabled">
                <i class="fas fa-chevron-left"></i>
            </span>
            @endif
        </div>
    </div>
</nav>
@endif
@endsection
