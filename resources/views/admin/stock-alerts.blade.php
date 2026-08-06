@extends('admin.layout')

@section('title', 'تنبيهات المخزون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-exclamation-triangle"></i> تنبيهات المخزون</h1>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة للوحة التحكم
    </a>
</div>

<!-- Out of Stock -->
@if($outOfStockProducts->count() > 0)
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="background-color: #dc3545; color: white;">
        <h3><i class="fas fa-times-circle"></i> منتجات نفدت من المخزون ({{ $outOfStockProducts->count() }})</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الفئة</th>
                    <th>الكمية الحالية</th>
                    <th>السعر</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outOfStockProducts as $product)
                <tr>
                    <td>{{ $product->name_ar }}</td>
                    <td>{{ $product->category?->name_ar ?? '-' }}</td>
                    <td><span class="badge badge-danger">{{ $product->stock_quantity }}</span></td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> تحديث المخزون
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Critical Stock -->
@if($criticalStockProducts->count() > 0)
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="background-color: #fd7e14; color: white;">
        <h3><i class="fas fa-exclamation-circle"></i> مخزون حرج ({{ $criticalStockProducts->count() }})</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الفئة</th>
                    <th>الكمية الحالية</th>
                    <th>السعر</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($criticalStockProducts as $product)
                <tr>
                    <td>{{ $product->name_ar }}</td>
                    <td>{{ $product->category?->name_ar ?? '-' }}</td>
                    <td><span class="badge badge-warning">{{ $product->stock_quantity }}</span></td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> تحديث المخزون
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Low Stock -->
@if($lowStockProducts->count() > 0)
<div class="card">
    <div class="card-header" style="background-color: #ffc107; color: black;">
        <h3><i class="fas fa-exclamation-triangle"></i> مخزون منخفض ({{ $lowStockProducts->count() }})</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>الصنف</th>
                    <th>الفئة</th>
                    <th>الكمية الحالية</th>
                    <th>السعر</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStockProducts as $product)
                <tr>
                    <td>{{ $product->name_ar }}</td>
                    <td>{{ $product->category?->name_ar ?? '-' }}</td>
                    <td><span class="badge badge-info">{{ $product->stock_quantity }}</span></td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>
                        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> تحديث المخزون
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($outOfStockProducts->count() === 0 && $criticalStockProducts->count() === 0 && $lowStockProducts->count() === 0)
<div class="card">
    <div class="card-body">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>ممتاز!</strong> جميع المنتجات بمستويات مخزون طبيعية.
        </div>
    </div>
</div>
@endif
@endsection
