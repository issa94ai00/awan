@extends('admin.layout')

@section('title', 'الإنتاج')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-industry"></i> الإنتاج</h1>
    <p>إدارة أوامر الإنتاج وتصدير المنتجات النهائية.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $productionOrders->total() }}</h3>
            <p>إجمالي أوامر الإنتاج</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $productionOrders->where('status', 'pending')->count() }}</h3>
            <p>أوامر معلقة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $productionOrders->where('status', 'completed')->count() }}</h3>
            <p>أوامر مكتملة</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <i class="fas fa-spinner"></i>
        </div>
        <div class="stat-info">
            <h3>{{ $productionOrders->where('status', 'in_progress')->count() }}</h3>
            <p>قيد التنفيذ</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> أوامر الإنتاج</h3>
        <a href="{{ route('admin.production.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> إنشاء أمر إنتاج
        </a>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.production.index') }}" class="filter-form">
            <div class="filter-row">
                <input type="text" name="search" placeholder="بحث برقم الأمر أو اسم المنتج..." value="{{ request('search') }}">
                <select name="status">
                    <option value="">كل الحالات</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>معلق</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>قيد التنفيذ</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتمل</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                </select>
                <button type="submit" class="btn btn-sm">بحث</button>
                <a href="{{ route('admin.production.index') }}" class="btn btn-sm btn-secondary">إعادة تعيين</a>
            </div>
        </form>

        @if($productionOrders->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>رقم الأمر</th>
                        <th>المنتج</th>
                        <th>الكمية</th>
                        <th>الحالة</th>
                        <th>تاريخ البدء</th>
                        <th>تاريخ الانتهاء</th>
                        <th>التكلفة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productionOrders as $order)
                        <tr>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->product?->name_ar ?? 'غير محدد' }}</td>
                            <td>{{ $order->quantity }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status }}">
                                    {{ $order->status_text }}
                                </span>
                            </td>
                            <td>{{ $order->start_date ? $order->start_date->format('Y-m-d') : '-' }}</td>
                            <td>{{ $order->end_date ? $order->end_date->format('Y-m-d') : '-' }}</td>
                            <td>${{ number_format($order->cost, 2) }}</td>
                            <td>
                                <div class="action-buttons">
                                    <a href="{{ route('admin.production.show', $order) }}" class="btn btn-sm btn-info" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.production.edit', $order) }}" class="btn btn-sm btn-warning" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.production.destroy', $order) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الأمر؟')" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $productionOrders->links() }}
        @else
            <p class="empty-state">لا توجد أوامر إنتاج.</p>
        @endif
    </div>
</div>
@endsection
