@extends('admin.layout')

@section('title', 'عرض الاستفسار')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-envelope"></i> عرض الاستفسار</h1>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.inquiries.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة للقائمة
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>{{ $inquiry->subject }}</h4>
                <span class="badge badge-{{ $inquiry->status === 'new' ? 'danger' : ($inquiry->status === 'read' ? 'warning' : 'success') }}">
                    {{ $inquiry->status === 'new' ? 'جديد' : ($inquiry->status === 'read' ? 'مقروء' : 'تم الرد') }}
                </span>
            </div>
            <div class="card-body">
                <div class="inquiry-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span><strong>الاسم:</strong> {{ $inquiry->name }}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-envelope"></i>
                        <span><strong>البريد:</strong> <a href="mailto:{{ $inquiry->email }}">{{ $inquiry->email }}</a></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-phone"></i>
                        <span><strong>الهاتف:</strong> <a href="tel:{{ $inquiry->phone }}">{{ $inquiry->phone ?? '-' }}</a></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><strong>التاريخ:</strong> {{ $inquiry->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                </div>

                <hr>

                <div class="inquiry-message">
                    <h5>الرسالة:</h5>
                    <div class="message-content">
                        {{ $inquiry->message }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>الإجراءات</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.inquiries.update', $inquiry) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="status">تغيير الحالة</label>
                        <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                            <option value="new" {{ $inquiry->status === 'new' ? 'selected' : '' }}>جديد</option>
                            <option value="read" {{ $inquiry->status === 'read' ? 'selected' : '' }}>مقروء</option>
                            <option value="replied" {{ $inquiry->status === 'replied' ? 'selected' : '' }}>تم الرد</option>
                        </select>
                    </div>
                </form>

                <hr>

                <div class="quick-actions">
                    <a href="mailto:{{ $inquiry->email }}?subject=Re: {{ $inquiry->subject }}" class="btn btn-primary btn-block mb-2">
                        <i class="fas fa-reply"></i> رد عبر البريد
                    </a>

                    @if($inquiry->phone)
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $inquiry->phone) }}" target="_blank" class="btn btn-success btn-block mb-2">
                        <i class="fab fa-whatsapp"></i> رد عبر واتساب
                    </a>
                    @endif

                    <form action="{{ route('admin.inquiries.destroy', $inquiry) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا الاستفسار؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fas fa-trash"></i> حذف الاستفسار
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.inquiry-meta {
    display: grid;
    gap: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.meta-item i {
    color: var(--accent-blue);
    width: 20px;
}

.message-content {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
    line-height: 1.8;
    white-space: pre-wrap;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-block {
    display: block;
    width: 100%;
    text-align: center;
}
</style>
@endpush
