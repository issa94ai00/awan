@extends('admin.layout')

@section('title', 'عرض أمر إنتاج')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-industry"></i> عرض أمر الإنتاج</h1>
    <p>تفاصيل أمر الإنتاج: {{ $productionOrder->order_number }}</p>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-eye"></i> تفاصيل أمر الإنتاج</h3>
        <div class="header-actions">
            <a href="{{ route('admin.production.edit', $productionOrder) }}" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.production.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> عودة
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <label>رقم الأمر:</label>
                <span class="value">{{ $productionOrder->order_number }}</span>
            </div>
            <div class="detail-item">
                <label>المنتج:</label>
                <span class="value">{{ $productionOrder->product?->name_ar ?? 'غير محدد' }}</span>
            </div>
            <div class="detail-item">
                <label>الكمية:</label>
                <span class="value">{{ $productionOrder->quantity }}</span>
            </div>
            <div class="detail-item">
                <label>الحالة:</label>
                <span class="value">
                    <span class="badge badge-{{ $productionOrder->status }}">
                        {{ $productionOrder->status_text }}
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <label>تاريخ البدء:</label>
                <span class="value">{{ $productionOrder->start_date ? $productionOrder->start_date->format('Y-m-d') : 'غير محدد' }}</span>
            </div>
            <div class="detail-item">
                <label>تاريخ الانتهاء:</label>
                <span class="value">{{ $productionOrder->end_date ? $productionOrder->end_date->format('Y-m-d') : 'غير محدد' }}</span>
            </div>
            <div class="detail-item">
                <label>التكلفة:</label>
                <span class="value">${{ number_format($productionOrder->cost, 2) }}</span>
            </div>
            <div class="detail-item">
                <label>تم الإنشاء بواسطة:</label>
                <span class="value">{{ $productionOrder->creator?->name ?? 'غير محدد' }}</span>
            </div>
            <div class="detail-item full-width">
                <label>ملاحظات:</label>
                <span class="value">{{ $productionOrder->notes ?? 'لا توجد ملاحظات' }}</span>
            </div>
            <div class="detail-item">
                <label>تاريخ الإنشاء:</label>
                <span class="value">{{ $productionOrder->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <div class="detail-item">
                <label>آخر تحديث:</label>
                <span class="value">{{ $productionOrder->updated_at->format('Y-m-d H:i') }}</span>
            </div>
        </div>

        <div class="status-update-section">
            <h4>تحديث الحالة</h4>
            <form action="{{ route('admin.production.update-status', $productionOrder) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <select name="status" required>
                        <option value="pending" {{ $productionOrder->status == 'pending' ? 'selected' : '' }}>معلق</option>
                        <option value="in_progress" {{ $productionOrder->status == 'in_progress' ? 'selected' : '' }}>قيد التنفيذ</option>
                        <option value="completed" {{ $productionOrder->status == 'completed' ? 'selected' : '' }}>مكتمل</option>
                        <option value="cancelled" {{ $productionOrder->status == 'cancelled' ? 'selected' : '' }}>ملغي</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync"></i> تحديث الحالة
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
