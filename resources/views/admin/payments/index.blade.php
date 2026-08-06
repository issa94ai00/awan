@extends('admin.layout')

@section('title', 'المدفوعات')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-money-bill-wave"></i> المدفوعات</h1>
    <a href="{{ route('admin.payments.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> دفعة جديدة
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم الدفعة</th>
                    <th>العميل</th>
                    <th>الفاتورة</th>
                    <th>المبلغ</th>
                    <th>الطريقة</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_number }}</td>
                    <td>{{ $payment->customer?->name ?? '-' }}</td>
                    <td>{{ $payment->invoice?->invoice_number ?? '-' }}</td>
                    <td>${{ number_format($payment->amount, 2) }}</td>
                    <td>{{ $payment->payment_method }}</td>
                    <td>
                        <span class="badge badge-{{ $payment->status === 'completed' ? 'success' : ($payment->status === 'pending' ? 'warning' : 'danger') }}">
                            {{ $payment->status }}
                        </span>
                    </td>
                    <td>{{ $payment->payment_date?->format('Y-m-d') ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.payments.edit', $payment) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">لا توجد مدفوعات</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
