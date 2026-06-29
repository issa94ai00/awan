@extends('admin.layout')

@section('title', 'طلبات البيع')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-shopping-cart"></i> طلبات البيع</h1>
        <p>إدارة ومتابعة طلبات بيع العملاء وعمليات التحويل لفواتير المبيعات</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.sales-orders.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> طلب بيع جديد
        </a>
    </div>
</div>

<!-- Stats Counter Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="fas fa-shopping-cart"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\SalesOrder::count() }}</h3>
            <p>إجمالي طلبات البيع</p>
            <small>الطلبات المسجلة كلياً</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\SalesOrder::where('status', 'pending')->count() }}</h3>
            <p>طلبات معلقة</p>
            <small>بانتظار التأكيد والمعالجة</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\SalesOrder::where('status', 'confirmed')->count() }}</h3>
            <p>طلبات مؤكدة</p>
            <small>جاهزة للتنفيذ أو التحويل لفاتورة</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="fas fa-truck-loading"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\SalesOrder::where('status', 'delivered')->count() }}</h3>
            <p>طلبات مكتملة</p>
            <small>تم شحنها وتسليمها للعميل</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.sales-orders.index') }}" method="GET" class="filter-form-wrapper" style="width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="search-form" style="margin: 0; flex: 1; min-width: 280px;">
                <div class="input-group" style="position: relative; display: flex; align-items: center;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث برقم الطلب أو اسم العميل..." class="form-control" style="padding-right: 2.5rem; width: 100%;">
                    <button type="submit" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <select name="status" class="form-control" style="min-width: 160px; padding: 0.5rem 1rem;" onchange="this.form.submit()">
                    <option value="">جميع الحالات</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلق</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>مؤكد</option>
                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>قيد المعالجة</option>
                    <option value="shipped" {{ request('status') == 'shipped' ? 'selected' : '' }}>تم الشحن</option>
                    <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>تم التسليم</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                </select>
                
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.sales-orders.index') }}" class="btn btn-secondary" style="padding: 0.625rem 1rem; border-radius: 8px;">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-striped" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 120px;">رقم الطلب</th>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700;">العميل</th>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">المبلغ الإجمالي</th>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">حالة الطلب</th>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">تاريخ الطلب</th>
                        <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 220px; text-align: center;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesOrders as $order)
                    <tr>
                        <td style="padding: 1.25rem 1.5rem;">
                            <strong style="color: var(--accent-blue);">{{ $order->order_number }}</strong>
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-user-circle" style="font-size: 1.25rem; color: var(--text-light);"></i>
                                <strong>{{ $order->customer?->name ?? '-' }}</strong>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text-dark);">
                            ${{ number_format($order->total, 2) }}
                        </td>
                        <td style="padding: 1.25rem 1.5rem;">
                            @php
                                $badgeClass = match($order->status) {
                                    'pending' => 'warning',
                                    'confirmed' => 'info',
                                    'processing' => 'secondary',
                                    'shipped' => 'info',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge badge-{{ $badgeClass }}" style="font-size: 0.8rem; padding: 0.375rem 0.75rem;">
                                <i class="fas @if($order->status === 'delivered') fa-check-circle @elseif($order->status === 'pending') fa-clock @elseif($order->status === 'cancelled') fa-times-circle @else fa-sync-alt @endif"></i>
                                {{ $order->status_text }}
                            </span>
                        </td>
                        <td style="padding: 1.25rem 1.5rem; color: var(--text-muted);">
                            {{ $order->created_at->format('Y-m-d') }}
                        </td>
                        <td style="padding: 1.25rem 1.5rem; text-align: center;">
                            <div class="btn-group" style="justify-content: center; gap: 0.35rem;">
                                <a href="{{ route('admin.sales-orders.show', $order) }}" class="btn btn-sm btn-secondary" title="عرض التفاصيل" style="border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; border: 1px solid var(--border-color);">
                                    <i class="fas fa-eye" style="color: var(--accent-blue);"></i>
                                </a>
                                <a href="{{ route('admin.sales-orders.edit', $order) }}" class="btn btn-sm btn-secondary" title="تعديل" style="border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; border: 1px solid var(--border-color);">
                                    <i class="fas fa-edit" style="color: var(--warning-dark);"></i>
                                </a>
                                
                                @if($order->status === 'confirmed')
                                <form action="{{ route('admin.sales-orders.convert-to-invoice', $order) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" title="تحويل لفاتورة" style="border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; display: inline-flex; align-items: center; gap: 0.25rem;">
                                        <i class="fas fa-file-invoice-dollar"></i> فاتورة
                                    </button>
                                </form>
                                @endif
                                
                                <form action="{{ route('admin.sales-orders.destroy', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف" style="border-radius: var(--radius-sm); padding: 0.4rem 0.6rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 3rem 0;">
                            <div class="empty-state" style="margin: 0; padding: 0;">
                                <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p style="font-weight: 500; font-size: 1.1rem; color: var(--text-muted);">لا توجد طلبات بيع مطابقة للبحث</p>
                                <a href="{{ route('admin.sales-orders.create') }}" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-plus"></i> إنشاء طلب جديد
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if(method_exists($salesOrders, 'links') && $salesOrders->hasPages())
<nav class="pagination-tailwind" aria-label="Pagination" style="margin-top: 1.5rem;">
    <!-- Mobile view -->
    <div class="mobile-pagination">
        @if($salesOrders->onFirstPage())
        <span class="btn-prev disabled">
            <i class="fas fa-chevron-right"></i>
            السابق
        </span>
        @else
        <a href="{{ $salesOrders->previousPageUrl() }}" class="btn-prev">
            <i class="fas fa-chevron-right"></i>
            السابق
        </a>
        @endif

        @if($salesOrders->hasMorePages())
        <a href="{{ $salesOrders->nextPageUrl() }}" class="btn-next">
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
            عرض <span>{{ $salesOrders->firstItem() }}</span> إلى <span>{{ $salesOrders->lastItem() }}</span> من <span>{{ $salesOrders->total() }}</span> طلب بيع
        </p>

        <div class="pagination-buttons">
            <!-- Previous -->
            @if($salesOrders->onFirstPage())
            <span class="page-btn prev disabled">
                <i class="fas fa-chevron-right"></i>
            </span>
            @else
            <a href="{{ $salesOrders->previousPageUrl() }}" class="page-btn prev">
                <i class="fas fa-chevron-right"></i>
            </a>
            @endif

            <!-- Page Numbers -->
            @foreach($salesOrders->getUrlRange(max(1, $salesOrders->currentPage() - 2), min($salesOrders->lastPage(), $salesOrders->currentPage() + 2)) as $page => $url)
                @if($page == $salesOrders->currentPage())
                <span class="page-btn active">{{ $page }}</span>
                @else
                <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                @endif
            @endforeach

            <!-- Next -->
            @if($salesOrders->hasMorePages())
            <a href="{{ $salesOrders->nextPageUrl() }}" class="page-btn next">
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
