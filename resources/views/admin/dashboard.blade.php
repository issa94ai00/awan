@extends('admin.layout')

@section('title', 'لوحة التحكم')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-tachometer-alt"></i> لوحة التحكم</h1>
    <p>نظرة عامة على أداء الموقع والإحصائيات</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $categoriesCount ?? 0 }}</h3>
            <p>الفئات</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-boxes"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $productsCount ?? 0 }}</h3>
            <p>المنتجات</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $inquiriesCount ?? 0 }}</h3>
            <p>الاستفسارات الجديدة</p>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-eye"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $visitorsCount ?? 0 }}</h3>
            <p>الزيارات اليوم</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> آخر المنتجات المضافة</h3>
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm">عرض الكل</a>
        </div>
        <div class="card-body">
            @if(isset($latestProducts) && $latestProducts->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>المنتج</th>
                            <th>الفئة</th>
                            <th>السعر</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestProducts as $product)
                        <tr>
                            <td>{{ $product->name_ar }}</td>
                            <td>{{ $product->category?->name_ar ?? '-' }}</td>
                            <td>${{ number_format($product->price, 2) }}</td>
                            <td>{{ $product->created_at->format('Y-m-d') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">لا توجد منتجات حديثة</p>
            @endif
        </div>
    </div>

    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-envelope"></i> آخر الاستفسارات</h3>
            <a href="{{ route('admin.inquiries.index') }}" class="btn btn-sm">عرض الكل</a>
        </div>
        <div class="card-body">
            @if(isset($latestInquiries) && $latestInquiries->count() > 0)
                <table class="table">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الموضوع</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestInquiries as $inquiry)
                        <tr>
                            <td>{{ $inquiry->name }}</td>
                            <td>{{ Str::limit($inquiry->subject, 30) }}</td>
                            <td>
                                <span class="badge badge-{{ $inquiry->status === 'new' ? 'danger' : ($inquiry->status === 'read' ? 'warning' : 'success') }}">
                                    {{ $inquiry->status === 'new' ? 'جديد' : ($inquiry->status === 'read' ? 'مقروء' : 'تم الرد') }}
                                </span>
                            </td>
                            <td>{{ $inquiry->created_at->format('Y-m-d') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty-state">لا توجد استفسارات جديدة</p>
            @endif
        </div>
    </div>
</div>
@endsection
