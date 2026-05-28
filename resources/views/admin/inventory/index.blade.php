@extends('admin.layout')

@section('title', 'المخزون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-warehouse"></i> المخزون</h1>
    <p>مراقبة المخزون والأساسيات المتعلقة بحركات الصنف.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $products->total() }}</h3>
            <p>المنتجات المدرجة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $stockCount }}</h3>
            <p>شاملة حركة المخزون</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-box"></i> مخزون المنتجات</h3>
        <a href="{{ route('admin.inventory.movements') }}" class="btn btn-sm">عرض الحركات</a>
    </div>
    <div class="card-body">
        @if($products->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الفئة</th>
                        <th>السعر</th>
                        <th>متوفر</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td>{{ $product->name_ar }}</td>
                            <td>{{ $product->category?->name_ar ?? 'غير محدد' }}</td>
                            <td>${{ number_format($product->price, 2) }}</td>
                            <td>{{ $product->in_stock ? 'نعم' : 'لا' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $products->links() }}
        @else
            <p class="empty-state">لا توجد منتجات في المخزون.</p>
        @endif
    </div>
</div>
@endsection
