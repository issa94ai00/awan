@extends('admin.layout')

@section('title', 'إيصالات الاستلام')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-truck-loading"></i> إيصالات الاستلام</h1>
        <p>إدارة وتوثيق وصولات استلام البضائع والمنتجات الموردة للمخازن وتحديث مستويات المخزون</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.purchases.index') }}" class="btn btn-secondary" style="border: 1px solid var(--border-color);">
            <i class="fas fa-arrow-right"></i> عودة للمشتريات
        </a>
        <a href="{{ route('admin.purchase-receipts.create') }}" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> إيصال استلام جديد
        </a>
    </div>
</div>

<!-- Stats Counter Row -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\PurchaseReceipt::count() }}</h3>
            <p>إجمالي الإيصالات</p>
            <small>المستندات المسجلة كلياً</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\PurchaseReceipt::whereDate('created_at', now()->format('Y-m-d'))->count() }}</h3>
            <p>استلامات اليوم</p>
            <small>الشحنات الواردة اليوم</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\Product::where('is_active', true)->count() }}</h3>
            <p>أصناف نشطة</p>
            <small>أصناف المنتجات المتاحة للتوريد</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.purchase-receipts.index') }}" method="GET" class="filter-form-wrapper" style="width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="search-form" style="margin: 0; flex: 1; min-width: 280px;">
                <div class="input-group" style="position: relative; display: flex; align-items: center;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث برقم الإيصال أو اسم المورد..." class="form-control" style="padding-right: 2.5rem; width: 100%;">
                    <button type="submit" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 0.75rem; align-items: center;">
                @if(request('search'))
                    <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary" style="padding: 0.625rem 1rem; border-radius: 8px;">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        @if($receipts->count())
            <div class="table-responsive">
                <table class="table table-striped" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">رقم الإيصال</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700;">المورد</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 180px;">مرجع أمر الشراء</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 180px;">تاريخ الاستلام</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 180px; text-align: center;">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($receipts as $receipt)
                            <tr>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <strong style="color: var(--accent-blue);">{{ $receipt->receipt_number }}</strong>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-truck" style="font-size: 1.1rem; color: var(--text-light);"></i>
                                        <strong>{{ $receipt->supplier?->name ?? '-' }}</strong>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    @if($receipt->purchaseOrder)
                                        <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.65rem;">
                                            <i class="fas fa-file-signature"></i> {{ $receipt->purchaseOrder->order_number }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-muted); font-size: 0.85rem; font-style: italic;">مباشر بدون أمر</span>
                                    @endif
                                </td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-dark);">
                                    {{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                    <div class="action-buttons-group" style="display: flex; justify-content: center; gap: 0.5rem;">
                                        <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="btn btn-sm btn-info" style="padding: 0.4rem 0.6rem;" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.purchase-receipts.edit', $receipt) }}" class="btn btn-sm btn-warning" style="padding: 0.4rem 0.6rem;" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.purchase-receipts.destroy', $receipt) }}" method="POST" style="display: inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" style="padding: 0.4rem 0.6rem;" onclick="return confirm('هل أنت متأكد من حذف إيصال الاستلام هذا وتعديل كميات المخزن؟')" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding: 4rem 2rem; text-align: center;">
                <i class="fas fa-receipt" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1.25rem; opacity: 0.5;"></i>
                <p style="font-weight: 500; font-size: 1.1rem; color: var(--text-muted);">لا توجد إيصالات استلام مسجلة حتى الآن.</p>
                <a href="{{ route('admin.purchase-receipts.create') }}" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-plus-circle"></i> إنشاء أول إيصال استلام
                </a>
            </div>
        @endif
    </div>
</div>

@if(method_exists($receipts, 'links') && $receipts->hasPages())
<nav class="pagination-tailwind" aria-label="Pagination" style="margin-top: 1.5rem;">
    <!-- Mobile view -->
    <div class="mobile-pagination">
        @if($receipts->onFirstPage())
        <span class="btn-prev disabled">
            <i class="fas fa-chevron-right"></i>
            السابق
        </span>
        @else
        <a href="{{ $receipts->previousPageUrl() }}" class="btn-prev">
            <i class="fas fa-chevron-right"></i>
            السابق
        </a>
        @endif

        @if($receipts->hasMorePages())
        <a href="{{ $receipts->nextPageUrl() }}" class="btn-next">
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
            عرض <span>{{ $receipts->firstItem() }}</span> إلى <span>{{ $receipts->lastItem() }}</span> من <span>{{ $receipts->total() }}</span> إيصال استلام
        </p>

        <div class="pagination-buttons">
            <!-- Previous -->
            @if($receipts->onFirstPage())
            <span class="page-btn prev disabled">
                <i class="fas fa-chevron-right"></i>
            </span>
            @else
            <a href="{{ $receipts->previousPageUrl() }}" class="page-btn prev">
                <i class="fas fa-chevron-right"></i>
            </a>
            @endif

            <!-- Page Numbers -->
            @foreach($receipts->getUrlRange(max(1, $receipts->currentPage() - 2), min($receipts->lastPage(), $receipts->currentPage() + 2)) as $page => $url)
                @if($page == $receipts->currentPage())
                <span class="page-btn active">{{ $page }}</span>
                @else
                <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                @endif
            @endforeach

            <!-- Next -->
            @if($receipts->hasMorePages())
            <a href="{{ $receipts->nextPageUrl() }}" class="page-btn next">
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
