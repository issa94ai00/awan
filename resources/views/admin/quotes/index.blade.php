@extends('admin.layout')

@section('title', 'عروض الأسعار')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> عروض الأسعار</h1>
    <a href="{{ route('admin.quotes.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> عرض سعر جديد
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم العرض</th>
                    <th>العميل</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($quotes as $quote)
                <tr>
                    <td>{{ $quote->quote_number }}</td>
                    <td>{{ $quote->customer?->name ?? '-' }}</td>
                    <td>${{ number_format($quote->total, 2) }}</td>
                    <td>
                        <span class="badge badge-{{ $quote->status === 'accepted' ? 'success' : ($quote->status === 'draft' ? 'warning' : 'secondary') }}">
                            {{ $quote->status }}
                        </span>
                    </td>
                    <td>{{ $quote->created_at->format('Y-m-d') }}</td>
                    <td>
                        <a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        @if($quote->status === 'accepted')
                        <form action="{{ route('admin.quotes.convert-to-sales-order', $quote) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="fas fa-exchange-alt"></i> تحويل لطلب
                            </button>
                        </form>
                        @endif
                        <form action="{{ route('admin.quotes.destroy', $quote) }}" method="POST" style="display: inline;">
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
                    <td colspan="6" class="text-center">لا توجد عروض أسعار</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
