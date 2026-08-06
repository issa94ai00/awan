@extends('admin.layout')

@section('title', 'قائمة الموردين')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-user-tie"></i> الموردون</h1>
        <p>إدارة بيانات الموردين المعتمدين وتتبع حساباتهم وتفاصيل الاتصال بهم</p>
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
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\Supplier::count() }}</h3>
            <p>إجمالي الموردين</p>
            <small>الموردين المسجلين كلياً</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3>{{ \App\Models\Supplier::where('status', 'active')->orWhere('status', '1')->count() }}</h3>
            <p>موردين نشطين</p>
            <small>علاقات تجارية نشطة</small>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-purple">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-info">
            <h3>${{ number_format(\App\Models\Supplier::sum('balance'), 2) }}</h3>
            <p>إجمالي الأرصدة</p>
            <small>المستحقات المالية للموردين</small>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.purchases.suppliers') }}" method="GET" class="filter-form-wrapper" style="width: 100%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div class="search-form" style="margin: 0; flex: 1; min-width: 280px;">
                <div class="input-group" style="position: relative; display: flex; align-items: center;">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث باسم المورد، البريد، الهاتف أو الشركة..." class="form-control" style="padding-right: 2.5rem; width: 100%;">
                    <button type="submit" style="position: absolute; right: 12px; background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-search"></i></button>
                </div>
            </div>
            
            <div class="filter-group" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <select name="status" class="form-control" style="min-width: 160px; padding: 0.5rem 1rem;" onchange="this.form.submit()">
                    <option value="">جميع الحالات</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                </select>
                
                @if(request('search') || request('status'))
                    <a href="{{ route('admin.purchases.suppliers') }}" class="btn btn-secondary" style="padding: 0.625rem 1rem; border-radius: 8px;">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </a>
                @endif
            </div>
        </form>
    </div>

    <div class="card-body" style="padding: 0;">
        @if($suppliers->count())
            <div class="table-responsive">
                <table class="table table-striped" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700;">اسم المورد</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700;">الشركة</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">رقم الهاتف</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 220px;">البريد الإلكتروني</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 150px;">الرصيد المالي</th>
                            <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 130px; text-align: center;">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suppliers as $supplier)
                            @php
                                $isActive = in_array(strtolower($supplier->status), ['active', '1', 'true', 'yes']);
                            @endphp
                            <tr>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-user-tie" style="font-size: 1.25rem; color: var(--accent-blue);"></i>
                                        <strong>{{ $supplier->name }}</strong>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-medium);">{{ $supplier->company ?? '-' }}</td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-dark);">{{ $supplier->phone ?? '-' }}</td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-muted); font-size: 0.85rem;">{{ $supplier->email ?? '-' }}</td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: {{ $supplier->balance > 0 ? 'var(--danger-dark)' : 'var(--text-dark)' }}">
                                    ${{ number_format($supplier->balance ?? 0, 2) }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                    <span class="badge badge-{{ $isActive ? 'success' : 'secondary' }}" style="font-size: 0.8rem; padding: 0.375rem 0.75rem;">
                                        <i class="fas {{ $isActive ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                                        {{ $isActive ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state" style="padding: 4rem 2rem; text-align: center;">
                <i class="fas fa-user-tie" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1.25rem; opacity: 0.5;"></i>
                <p style="font-weight: 500; font-size: 1.1rem; color: var(--text-muted);">لا توجد نتائج مطابقة لفلترة الموردين.</p>
            </div>
        @endif
    </div>
</div>

@if(method_exists($suppliers, 'links') && $suppliers->hasPages())
<nav class="pagination-tailwind" aria-label="Pagination" style="margin-top: 1.5rem;">
    <!-- Mobile view -->
    <div class="mobile-pagination">
        @if($suppliers->onFirstPage())
        <span class="btn-prev disabled">
            <i class="fas fa-chevron-right"></i>
            السابق
        </span>
        @else
        <a href="{{ $suppliers->previousPageUrl() }}" class="btn-prev">
            <i class="fas fa-chevron-right"></i>
            السابق
        </a>
        @endif

        @if($suppliers->hasMorePages())
        <a href="{{ $suppliers->nextPageUrl() }}" class="btn-next">
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
            عرض <span>{{ $suppliers->firstItem() }}</span> إلى <span>{{ $suppliers->lastItem() }}</span> من <span>{{ $suppliers->total() }}</span> مورد
        </p>

        <div class="pagination-buttons">
            <!-- Previous -->
            @if($suppliers->onFirstPage())
            <span class="page-btn prev disabled">
                <i class="fas fa-chevron-right"></i>
            </span>
            @else
            <a href="{{ $suppliers->previousPageUrl() }}" class="page-btn prev">
                <i class="fas fa-chevron-right"></i>
            </a>
            @endif

            <!-- Page Numbers -->
            @foreach($suppliers->getUrlRange(max(1, $suppliers->currentPage() - 2), min($suppliers->lastPage(), $suppliers->currentPage() + 2)) as $page => $url)
                @if($page == $suppliers->currentPage())
                <span class="page-btn active">{{ $page }}</span>
                @else
                <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                @endif
            @endforeach

            <!-- Next -->
            @if($suppliers->hasMorePages())
            <a href="{{ $suppliers->nextPageUrl() }}" class="page-btn next">
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
